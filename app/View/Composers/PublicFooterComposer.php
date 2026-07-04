<?php

namespace App\View\Composers;

use App\Models\SiteSettings;
use Illuminate\View\View;

class PublicFooterComposer
{
    public function compose(View $view): void
    {
        $view->with('siteSettings', SiteSettings::current()->load(['privacyPage', 'termsPage']));
    }
}
