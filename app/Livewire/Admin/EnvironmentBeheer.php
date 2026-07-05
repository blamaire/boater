<?php

namespace App\Livewire\Admin;

use App\Models\Environment;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor externe RZVG-omgevingen (test/acceptatie/productie).
 * Bewaart naam, URL, encrypted API-token en actief-vlag. De token is bij
 * bestaande omgevingen niet zichtbaar; leeg laten betekent "niet wijzigen".
 */
#[Layout('layouts.app', ['header' => 'Omgevingen'])]
class EnvironmentBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $url = '';

    public string $apiToken = '';

    public bool $isActive = true;

    public ?string $statusMessage = null;

    public function edit(int $id): void
    {
        $env = Environment::query()->findOrFail($id);
        $this->editingId = $env->id;
        $this->name = $env->name;
        $this->url = $env->url;
        $this->apiToken = '';
        $this->isActive = $env->is_active;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'url', 'apiToken', 'isActive']);
        $this->isActive = true;
    }

    public function save(AuditLogger $audit): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:255'],
            'apiToken' => ['nullable', 'string', 'min:16', 'max:255'],
            'isActive' => ['boolean'],
        ];

        if ($this->editingId === null) {
            $rules['apiToken'] = ['required', 'string', 'min:16', 'max:255'];
        }

        $this->validate($rules);

        DB::transaction(function () use ($audit): void {
            if ($this->editingId === null) {
                $env = Environment::query()->create([
                    'name' => $this->name,
                    'url' => $this->url,
                    'api_token' => $this->apiToken,
                    'is_active' => $this->isActive,
                ]);
                $audit->log('environment.created', $env, after: $this->safeSnapshot($env));
                $this->statusMessage = "Omgeving [{$env->name}] toegevoegd.";
            } else {
                $env = Environment::query()->findOrFail($this->editingId);
                $before = $this->safeSnapshot($env);
                $env->name = $this->name;
                $env->url = $this->url;
                $env->is_active = $this->isActive;
                if ($this->apiToken !== '') {
                    $env->api_token = $this->apiToken;
                }
                $env->save();
                $audit->log('environment.updated', $env, before: $before, after: $this->safeSnapshot($env));
                $this->statusMessage = "Omgeving [{$env->name}] bijgewerkt.";
            }
        });

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $env = Environment::query()->findOrFail($id);
        $before = $this->safeSnapshot($env);
        DB::transaction(function () use ($env, $before, $audit): void {
            $audit->log('environment.deleted', $env, before: $before);
            $env->delete();
        });
        $this->statusMessage = "Omgeving [{$env->name}] verwijderd.";

        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function safeSnapshot(Environment $env): array
    {
        return [
            'name' => $env->name,
            'url' => $env->url,
            'is_active' => $env->is_active,
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.environment-beheer', [
            'environments' => Environment::query()->orderBy('name')->get(),
        ]);
    }
}
