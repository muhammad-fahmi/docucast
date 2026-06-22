<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // vendor
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                // local
                $this->getNikFormComponent(),
                $this->getEmployeeNoFormComponent(),
                $this->getJobTitleFormComponent(),
                $this->getTelegramChatIdFormComponent(),
                $this->getPhoneNumberFormComponent(),
            ]);
    }

    /**
     * Summary of getNikFormComponent
     *
     * @return TextInput
     */
    protected function getNikFormComponent(): Component
    {
        return TextInput::make('nik')
            ->label('Username (NIK)')
            ->disabled();
    }

    /**
     * Summary of getEmployeeNoFormComponent
     *
     * @return TextInput
     */
    protected function getEmployeeNoFormComponent(): Component
    {
        return TextInput::make('employee_no')
            ->label('Employee No')
            ->disabled();
    }

    /**
     * Summary of getJobTitleFormComponent
     *
     * @return TextInput
     */
    protected function getJobTitleFormComponent(): Component
    {
        return TextInput::make('job_title')
            ->label('Job Title')
            ->disabled();
    }

    /**
     * Summary of getTelegramChatIdFormComponent
     *
     * @return TextInput
     */
    protected function getTelegramChatIdFormComponent(): Component
    {
        return TextInput::make('telegram_chat_id')
            ->label('Telegram Chat ID')
            ->password()
            ->revealable();
    }

    /**
     * Summary of getPhoneNumberFormComponent
     *
     * @return TextInput
     */
    protected function getPhoneNumberFormComponent(): Component
    {
        return TextInput::make('phone_number')
            ->label('Phone Number')
            ->numeric();
    }

    /**
     * Get the URL to redirect to after saving the profile.
     */
    protected function getRedirectUrl(): ?string
    {
        return filament()->getHomeUrl();
    }
}
