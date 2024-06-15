<?php

namespace Tempest\View\Elements;

use Tempest\View\Element;
use Tempest\View\View;
use Tempest\View\ViewRenderer;

final readonly class TextElement implements Element
{
    public function __construct(
        private View $view,
        private string $text,
    ) {}

    public function render(ViewRenderer $renderer): string
    {
        return preg_replace_callback(
            '/{{\s*(?<eval>\$this->.*?)\s*}}/',
            function (array $matches) : string {
                return $this->view->eval($matches['eval']) ?? '';
            },
            $this->text,
        );
    }
}