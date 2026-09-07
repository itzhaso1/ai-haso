<?php

namespace App\Http\Controllers\Workspace\Communication;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\Conversation;
use Illuminate\View\View;

class TemplateController extends Controller
{
    use InteractsWithWorkspace;

    public function index(): View
    {
        $this->authorize('viewAny', Conversation::class);

        return view('workspace.communication.templates.index');
    }
}
