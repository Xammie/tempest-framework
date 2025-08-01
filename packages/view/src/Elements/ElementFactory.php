<?php

declare(strict_types=1);

namespace Tempest\View\Elements;

use Tempest\Container\Container;
use Tempest\Core\AppConfig;
use Tempest\View\Attributes\PhpAttribute;
use Tempest\View\Components\DynamicViewComponent;
use Tempest\View\Element;
use Tempest\View\Parser\TempestViewCompiler;
use Tempest\View\Parser\Token;
use Tempest\View\Parser\TokenType;
use Tempest\View\Slot;
use Tempest\View\ViewComponent;
use Tempest\View\ViewConfig;

final class ElementFactory
{
    private TempestViewCompiler $compiler;

    public function __construct(
        private readonly AppConfig $appConfig,
        private readonly ViewConfig $viewConfig,
        private readonly Container $container,
    ) {}

    public function setViewCompiler(TempestViewCompiler $compiler): self
    {
        $this->compiler = $compiler;

        return $this;
    }

    public function make(Token $token): ?Element
    {
        return $this->makeElement(
            token: $token,
            parent: null,
        );
    }

    private function makeElement(Token $token, ?Element $parent): ?Element
    {
        if (
            $token->type === TokenType::OPEN_TAG_END ||
                $token->type === TokenType::ATTRIBUTE_NAME ||
                $token->type === TokenType::ATTRIBUTE_VALUE ||
                $token->type === TokenType::SELF_CLOSING_TAG_END
        ) {
            return null;
        }

        if ($token->type === TokenType::CONTENT) {
            $text = $token->compile();

            if (trim($text) === '') {
                return null;
            }

            return new TextElement(text: $text);
        }

        if (! $token->tag || $token->type === TokenType::COMMENT || $token->type === TokenType::PHP) {
            return new RawElement(token: $token, tag: null, content: $token->compile());
        }

        $attributes = $token->htmlAttributes;

        foreach ($token->phpAttributes as $index => $content) {
            $attributes[] = new PhpAttribute((string) $index, $content);
        }

        if ($token->tag === 'code' || $token->tag === 'pre') {
            return new RawElement(
                token: $token,
                tag: $token->tag,
                content: $token->compileChildren(),
                attributes: $attributes,
            );
        }

        if ($viewComponentClass = $this->viewConfig->viewComponents[$token->tag] ?? null) {
            if ($token->getAttribute('is') || $token->getAttribute(':is')) {
                $viewComponentClass = new DynamicViewComponent($token);
            }

            if (! ($viewComponentClass instanceof ViewComponent)) {
                $viewComponentClass = $this->container->get($viewComponentClass);
            }

            $element = new ViewComponentElement(
                token: $token,
                environment: $this->appConfig->environment,
                compiler: $this->compiler,
                viewComponent: $viewComponentClass,
                attributes: $attributes,
            );
        } elseif ($token->tag === 'x-template') {
            $element = new TemplateElement(
                token: $token,
                attributes: $attributes,
            );
        } elseif ($token->tag === 'x-slot') {
            $element = new SlotElement(
                token: $token,
                name: $token->getAttribute('name') ?? Slot::DEFAULT,
                attributes: $attributes,
            );
        } else {
            $element = new GenericElement(
                token: $token,
                tag: $token->tag,
                attributes: $attributes,
            );
        }

        $children = [];

        foreach ($token->children as $child) {
            $childElement = $this->clone()->makeElement(
                token: $child,
                parent: $parent,
            );

            if ($childElement === null) {
                continue;
            }

            $children[] = $childElement;
        }

        $element->setChildren($children);

        return $element;
    }

    private function clone(): self
    {
        return clone $this;
    }
}
