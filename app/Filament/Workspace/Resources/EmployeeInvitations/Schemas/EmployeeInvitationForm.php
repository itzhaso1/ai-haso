<?php

namespace App\Filament\Workspace\Resources\EmployeeInvitations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EmployeeInvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('invited_by')
                    ->default(fn () => auth()->id()),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->required()
                    ->options([
                        'owner' => 'Owner',
                        'admin' => 'Admin',
                        'manager' => 'Manager',
                        'agent' => 'Agent',
                    ])
                    ->default('agent'),
                Select::make('status')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending'),
                Hidden::make('token')
                    ->default(fn () => Str::random(64))
                    ->required(),
                DateTimePicker::make('expires_at')
                    ->default(now()->addDays(7))
                    ->required(),
                DateTimePicker::make('accepted_at'),
            ]);
    }
}
