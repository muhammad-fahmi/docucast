# SOLID Violations Quick Reference

## DocuCast Application

---

## 🔴 CRITICAL (Fix First) - High Impact

### Issue #1: Document Model Violates SRP + DIP

**Files:** `app/Models/Document.php`  
**Problem:** Model mixes entity, status logic, authorization, and DB queries  
**Fix:** Extract to `DocumentStatusService` + `DocumentReviewAuthorizationService`  
**Impact:** -40% model methods, +2 focused services  
**Est. Time:** 2-3 hours

---

### Issue #2: DocumentController Violates SRP + DIP

**Files:** `app/Http/Controllers/DocumentController.php`  
**Problem:** Controller handles validation, file storage, transactions, business logic  
**Fix:** Extract to `DocumentUploadService` + `DocumentReviewService`  
**Impact:** Controller becomes thin layer, logic becomes reusable  
**Est. Time:** 3-4 hours

---

### Issue #3: Hard-coded Status Strings Violate OCP

**Files:** `app/Models/Document.php`, `app/Filament/Resources/Documents/Tables/DocumentsTable.php`  
**Problem:** Status strings duplicated, no way to add new status types easily  
**Fix:** Create `DocumentStatus` enum with helper methods  
**Impact:** Single source of truth for statuses, easier to extend  
**Est. Time:** 1-2 hours

---

## 🟠 IMPORTANT (Fix Next) - Medium Impact

### Issue #4: DocumentsTable Contains Business Logic (SRP)

**Files:** `app/Filament/Resources/Documents/Tables/DocumentsTable.php` (lines 139-154, 216-240)  
**Problem:** Complex review action with transactions and notifications  
**Fix:** Use `DocumentReviewService` called from action  
**Impact:** Logic becomes testable and reusable  
**Est. Time:** 2 hours

---

### Issue #5: CreateDocument Page Violates SRP

**Files:** `app/Filament/Resources/Documents/Pages/CreateDocument.php` (lines 24-47)  
**Problem:** Form events handle recipients, status updates, notifications  
**Fix:** Use event listeners + services  
**Impact:** Page logic becomes thin, notifications centralized  
**Est. Time:** 1-2 hours

---

### Issue #6: Direct Auth Facade Usage (DIP)

**Files:** Multiple files use `Auth::user()` directly  
**Problem:** Tight coupling to facade, hard to mock in tests  
**Fix:** Inject authenticated user via middleware or constructor  
**Impact:** Better testability  
**Est. Time:** 1-2 hours

---

## 🟡 NICE-TO-HAVE (Fix Later) - Low Impact

### Issue #7: Duplicate Policy Methods (ISP)

**Files:** `app/Policies/*.php`  
**Problem:** All policies repeat same 11 methods  
**Fix:** Create `BaseResourcePolicy` abstract class  
**Impact:** DRY code, less duplication  
**Est. Time:** 1 hour

---

### Issue #8: Hard-coded File Upload Rules (OCP)

**Files:** `app/Filament/Resources/Documents/Schemas/DocumentForm.php` (lines 28-35)  
**Problem:** File types/maxsize hard-coded in form  
**Fix:** Create configuration or service  
**Impact:** Centralized upload rules  
**Est. Time:** 30 minutes

---

### Issue #9: Inconsistent Relationship Naming (LSP)

**Files:** `app/Models/DocumentReview.php`  
**Problem:** Field is `user_id` but relationship is `reviewer()`  
**Fix:** Add both relationships for consistency  
**Impact:** Clearer code, less confusion  
**Est. Time:** 15 minutes

---

## 📊 Refactoring Priority Matrix

```
Impact     Easy ████████████ Hard
High       1-3/2-3h      #1,2 (4-5h each)
Medium     #7-9/30m-1h   #4-6 (1-2h each)
Low
```

---

## ✅ Files to Create (Immediate)

```
app/
├── Enums/
│   └── DocumentStatus.php
├── Services/
│   ├── DocumentStatusService.php
│   ├── DocumentReviewAuthorizationService.php
│   ├── DocumentUploadService.php
│   ├── DocumentReviewService.php
│   └── FileStorageService.php (optional)
├── Actions/ (optional organization)
│   ├── CreateDocumentAction.php
│   ├── ReviewDocumentAction.php
│   └── UploadRevisionAction.php
└── Http/Requests/
    ├── StoreDocumentRequest.php
    └── ReviewDocumentRequest.php
```

---

## 🔨 Files to Refactor (Priority Order)

1. `app/Models/Document.php` - Remove 4 methods
2. `app/Http/Controllers/DocumentController.php` - Extract 3 methods
3. `app/Filament/Resources/Documents/Tables/DocumentsTable.php` - Extract action logic
4. `app/Filament/Resources/Documents/Pages/CreateDocument.php` - Use events
5. `app/Policies/*.php` - Create base class

---

## 💾 Migration Steps

### Week 1: Foundation (10-12 hours)

- [ ] Create enums and services (Issues #1, #2, #3)
- [ ] Run tests - ensure nothing breaks
- [ ] Commit: "SOLID: Extract status and review logic"

### Week 2: Controllers & Forms (6-8 hours)

- [ ] Refactor DocumentController (Issue #2)
- [ ] Update CreateDocument page (Issue #5)
- [ ] Update tables (Issue #4)
- [ ] Commit: "SOLID: Extract controller logic to services"

### Week 3: Polish (3-4 hours)

- [ ] Fix policies (Issue #7)
- [ ] Extract configuration (Issue #8)
- [ ] Fix relationship naming (Issue #9)
- [ ] Commit: "SOLID: Code polish and cleanup"

---

## 🧪 Testing After Refactoring

```bash
# Test individual services
php artisan test tests/Unit/Services/DocumentStatusServiceTest.php

# Test feature workflows
php artisan test tests/Feature/DocumentReviewTest.php

# Full coverage
php artisan test --coverage

# Expected: 80%+ coverage (up from 40%)
```

---

## 📋 Verification Checklist

After each refactoring, verify:

- [ ] All tests pass: `php artisan test`
- [ ] Code formatted: `vendor/bin/pint --dirty`
- [ ] No linting errors: `php artisan lint`
- [ ] Logic works via Filament UI
- [ ] Database queries optimized
- [ ] No N+1 queries in services

---

## 🎯 Success Criteria

| Metric                | Before | After |
| --------------------- | ------ | ----- |
| Model method count    | 11     | 5     |
| Avg method lines      | 25     | 8     |
| Test coverage         | 40%    | 80%+  |
| Service classes       | 1      | 5+    |
| Hard-coded values     | 8+     | 0     |
| Circular dependencies | 2      | 0     |

---

## 📚 Quick Links to Detailed Docs

- **Full Analysis:** `SOLID_ANALYSIS.md`
- **Implementation Guide:** `SOLID_REFACTORING_GUIDE.md`
- **Code Examples:** See refactoring guide sections

---

## ⚠️ Common Pitfalls to Avoid

1. **Don't** move logic without writing tests first
2. **Don't** create god services - keep them focused
3. **Don't** forget to update casts in models
4. **Don't** break existing Filament Resource structure
5. **Don't** commit without running `pint --dirty`

---

## 🤝 Questions to Ask While Refactoring

- [ ] Can this class do just one thing?
- [ ] Can I test this without the database?
- [ ] Do I depend on concrete classes or abstractions?
- [ ] Is this hard-coded value really necessary?
- [ ] Could another class reuse this logic?
- [ ] Is this interface too fat?

---

## 💡 Pro Tips

1. **Make one change at a time** - easier to debug
2. **Write tests before refactoring** - catch regressions
3. **Use IDE refactoring tools** - safer than manual edits
4. **Git commit after each small win** - easy rollback
5. **Code review with team** - catch missed patterns
