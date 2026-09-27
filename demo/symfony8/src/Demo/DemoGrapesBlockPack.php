<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackInterface;

/**
 * Example host GrapesJS block pack for the Symfony 8 demo.
 */
final class DemoGrapesBlockPack implements GrapesBlockPackInterface
{
    public function getName(): string
    {
        return 'demo/sample-blocks';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getCapabilities(): array
    {
        return ['demo', 'grapesjs'];
    }

    public function getBlocks(): array
    {
        return [
            [
                'id'       => 'demo-notice-banner',
                'label'    => 'Demo notice',
                'category' => 'Demo pack',
                'content'  => '<div class="alert alert-info" role="status" style="padding:1rem;border-radius:.5rem;background:#e0f2fe;border:1px solid #7dd3fc;color:#0c4a6e;margin:1rem 0"><strong>Demo pack</strong> — host-registered Grapes block.</div>',
            ],
            [
                'id'       => 'demo-kpi-pill',
                'label'    => 'KPI pill',
                'category' => 'Demo pack',
                'content'  => '<div style="display:inline-flex;align-items:baseline;gap:.5rem;padding:.5rem 1rem;border-radius:999px;background:#0f172a;color:#f8fafc;font-family:system-ui,sans-serif"><span style="font-size:1.25rem;font-weight:700">98%</span><span style="opacity:.85;font-size:.875rem">satisfaction</span></div>',
            ],
        ];
    }
}
