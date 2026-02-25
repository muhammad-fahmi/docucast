<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\DocumentVersion;
use App\Models\RevisionHistory;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    /**
     * 1. HANDLE INITIAL UPLOAD
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'document_file' => 'required|file|mimes:pdf|max:10240', // 10MB max PDF
            'reviewer_id' => 'required|exists:users,id',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $user = Auth::user();

                // A. Store the physical file
                $path = $request->file('document_file')->store('documents', 'public');
                $originalName = $request->file('document_file')->getClientOriginalName();

                // B. Create the Master Document (The Stable ID)
                $document = Document::create([
                    'title' => $request->title,
                    'initiator_id' => $user->id,
                    'overall_status' => 'PENDING_REVIEW',
                ]);

                // C. Create Version 1
                $version = DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => 1,
                    'file_storage_path' => $path,
                    'original_filename' => $originalName,
                    'uploaded_by' => $user->id,
                ]);

                // D. Assign to Reviewer (The Inbox)
                DocumentApproval::create([
                    'document_id' => $document->id,
                    'reviewer_id' => $request->reviewer_id,
                    'status' => 'PENDING',
                ]);

                // E. Record History
                RevisionHistory::create([
                    'document_id' => $document->id,
                    'related_version_id' => $version->id,
                    'commenter_id' => $user->id,
                    'action_type' => 'SUBMITTED',
                    'comments' => 'Initial document upload.',
                ]);
            });

            return redirect()->back()->with('success', 'Document uploaded successfully!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * 2. HANDLE REVIEWER DECISION (Approve or Reject)
     */
    public function review(Request $request, Document $document)
    {
        $request->validate([
            'decision' => 'required|in:approve,revise',
            'comments' => 'required_if:decision,revise|string|nullable',
        ]);

        $user = Auth::user();
        $latestVersion = $document->latestVersion;

        // Ensure this user is actually the assigned reviewer
        $approval = DocumentApproval::where('document_id', $document->id)
            ->where('reviewer_id', $user->id)
            ->firstOrFail();

        if ($approval->status !== 'PENDING') {
            throw new HttpException(409, 'This document review has already been processed.');
        }

        DB::transaction(function () use ($request, $document, $approval, $latestVersion, $user) {
            if ($request->decision === 'approve') {
                $approval->update(['status' => 'APPROVED', 'processed_at' => now()]);
                $document->update(['overall_status' => 'APPROVED']);
                $actionType = 'APPROVED';
            } else {
                $approval->update(['status' => 'REJECTED_FOR_REVISION', 'processed_at' => now()]);
                $document->update(['overall_status' => 'NEEDS_REVISION']);
                $actionType = 'REQUESTED_REVISION';
            }

            RevisionHistory::create([
                'document_id' => $document->id,
                'related_version_id' => $latestVersion->id, // The version they looked at
                'commenter_id' => $user->id,
                'action_type' => $actionType,
                'comments' => $request->comments,
            ]);
        });

        return redirect()->back()->with('success', 'Review submitted successfully!');
    }

    /**
     * 3. HANDLE RE-UPLOAD (Revising the Document)
     */
    public function reupload(Request $request, Document $document)
    {
        $request->validate([
            'document_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $user = Auth::user();

        // Ensure only the initiator can re-upload, and only if it needs revision
        if ($document->initiator_id !== $user->id || $document->overall_status !== 'NEEDS_REVISION') {
            abort(403, 'Unauthorized action or document does not need revision.');
        }

        DB::transaction(function () use ($request, $document, $user) {
            // A. Store the NEW physical file
            $path = $request->file('document_file')->store('documents', 'public');
            $originalName = $request->file('document_file')->getClientOriginalName();

            // B. Determine the new version number
            $currentVersion = $document->versions()->max('version_number');
            $newVersionNumber = $currentVersion + 1;

            // C. Create the NEW Version record
            $newVersion = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $newVersionNumber,
                'file_storage_path' => $path,
                'original_filename' => $originalName,
                'uploaded_by' => $user->id,
            ]);

            // D. Update Master Document Status
            $document->update(['overall_status' => 'PENDING_REVIEW']);

            // E. Reset the Approval Inbox for the reviewer
            $approval = DocumentApproval::where('document_id', $document->id)->firstOrFail();
            $approval->update([
                'status' => 'PENDING',
                'processed_at' => null // Reset the timestamp so it shows as "unread/unprocessed"
            ]);

            // F. Log the resubmission
            RevisionHistory::create([
                'document_id' => $document->id,
                'related_version_id' => $newVersion->id,
                'commenter_id' => $user->id,
                'action_type' => 'RESUBMITTED',
                'comments' => "Uploaded revision v{$newVersionNumber}",
            ]);
        });

        return redirect()->back()->with('success', 'Revision uploaded successfully!');
    }
}
