<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Page;
use App\Services\Audit\AuditLogger;
use App\Services\Cms\PagePushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class PagePushController extends Controller
{
    public function __invoke(
        Request $request,
        Page $page,
        PagePushService $pusher,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'environment_id' => ['required', 'integer', 'exists:environments,id'],
        ]);

        /** @var Environment $environment */
        $environment = Environment::query()->findOrFail($data['environment_id']);

        try {
            $result = $pusher->push($page, $environment);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'Push mislukt: '.$e->getMessage());
        }

        $audit->log('page.pushed', $page, after: [
            'environment_id' => $environment->id,
            'environment_name' => $environment->name,
            'result' => $result,
        ]);

        $verb = ($result['created'] ?? false) ? 'aangemaakt' : 'als nieuwe conceptversie geplaatst';

        return redirect()->back()->with('status',
            "Pagina [{$page->title}] is op omgeving [{$environment->name}] {$verb}."
        );
    }
}
