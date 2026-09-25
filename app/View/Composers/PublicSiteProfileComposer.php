<?php

namespace App\View\Composers;

use App\Services\PublicContentReader;
use Illuminate\Contracts\View\View;

class PublicSiteProfileComposer
{
    public function __construct(
        private readonly PublicContentReader $content,
    ) {}

    public function compose(View $view): void
    {
        $view->with('siteProfile', $this->content->schoolProfile());
    }
}
