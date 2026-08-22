<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreModifierGroupRequest;
use App\Http\Requests\UpdateModifierGroupRequest;
use App\Models\ModifierGroup;
use App\Services\MenuService;
use Illuminate\Http\RedirectResponse;

class ModifierGroupController extends Controller
{
    public function __construct(
        private readonly MenuService $menuService
    ) {}

    public function store(StoreModifierGroupRequest $request): RedirectResponse
    {
        $this->menuService->createGroup($request->validated());

        return redirect()->back()->with('success', 'Add-on group created.');
    }

    public function update(UpdateModifierGroupRequest $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->menuService->updateGroup($modifierGroup, $request->validated());

        return redirect()->back()->with('success', 'Add-on group updated.');
    }

    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        // Detaching first keeps products valid; sale history keeps its own snapshot.
        $modifierGroup->products()->detach();
        $modifierGroup->delete();

        return redirect()->back()->with('success', 'Add-on group deleted.');
    }
}
