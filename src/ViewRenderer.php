<?php

declare(strict_types=1);

namespace Yiisoft\Yii\View;

use RuntimeException;
use Throwable;
use Yiisoft\Aliases\Aliases;
use Yiisoft\DataResponse\DataResponse;
use Yiisoft\DataResponse\DataResponseFactoryInterface;
use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Link;
use Yiisoft\Html\Tag\Meta;
use Yiisoft\Strings\Inflector;
use Yiisoft\View\Exception\ViewNotFoundException;
use Yiisoft\View\ViewContextInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Exception\InvalidLinkTagException;
use Yiisoft\Yii\View\Exception\InvalidMetaTagException;

use function array_key_exists;
use function array_merge;
use function get_class;
use function gettype;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function pathinfo;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;

/**
 * ViewRenderer renders the view.
 *
 * If {@see ViewRenderer::render()} or {@see ViewRenderer::renderPartial()} methods are called,
 * an instance of {@see DataResponse} is returned. It supports deferred rendering that
 * occurs when calling {@see DataResponse::getBody()} or {@see DataResponse::getData()}.
 *
 * If {@see ViewRenderer::renderAsString()} or {@see ViewRenderer::renderPartialAsString()} methods are called,
 * the rendering will occur immediately and the string result of the rendering will be returned.
 */
final class ViewRenderer implements ViewContextInterface
{
    private DataResponseFactoryInterface $responseFactory;
    private Aliases $aliases;
    private WebView $view;

    private string $viewPath;
    private ?string $layout;
    private ?string $name = null;

    /**
     * @var object[]
     */
    private array $injections;

    /**
     * @param DataResponseFactoryInterface $responseFactory The data response factory instance.
     * @param Aliases $aliases The aliases instance.
     * @param WebView $view The web view instance.
     * @param string $viewPath The full path to the directory of views or its alias.
     * @param string|null $layout The layout name (e.g. "layout/main") to be applied to views.
     * If null, the layout will not be applied.
     * @param object[] $injections The injection instances.
     */
    public function __construct(
        DataResponseFactoryInterface $responseFactory,
        Aliases $aliases,
        WebView $view,
        string $viewPath,
        ?string $layout = null,
        array $injections = []
    ) {
        $this->responseFactory = $responseFactory;
        $this->aliases = $aliases;
        $this->view = $view;
        $this->viewPath = rtrim($viewPath, '/');
        $this->layout = $layout;
        $this->injections = $injections;
    }

    /**
     * Returns a path to a base directory of view templates that is prefixed to the relative view name.
     *
     * If a controller name has been set {@see withController(), withControllerName()}, it will be appended to the path.
     *
     * @return string View templates base directory.
     */
    public function getViewPath(): string
    {
        return $this->aliases->get($this->viewPath) . ($this->name ? '/' . $this->name : '');
    }

    /**
     * Returns a response instance {@see DataResponse} that supports deferred rendering.
     *
     * Rendering will occur when calling {@see DataResponse::getBody()} or {@see DataResponse::getData()}.
     *
     * @param string $view The view name {@see WebView::render()}.
     * @param array $parameters The parameters (name-value pairs) that will be extracted
     * and made available in the view file.
     *
     * @return DataResponse The response instance.
     */
    public function render(string $view, array $parameters = []): DataResponse
    {
        $contentParameters = $this->getContentParameters($parameters);
        $layoutParameters = $this->getLayoutParameters();
        $metaTags = $this->getMetaTags();
        $linkTags = $this->getLinkTags();

        return $this->responseFactory->createResponse(fn (): string => $this->renderProxy(
            $view,
            $contentParameters,
            $layoutParameters,
            $metaTags,
            $linkTags,
        ));
    }

    /**
     * Returns a response instance {@see DataResponse} that supports deferred
     * rendering {@see render()} without applying a layout.
     *
     * Rendering will occur when calling {@see DataResponse::getBody()} or {@see DataResponse::getData()}.
     *
     * @param string $view The view name {@see WebView::render()}.
     * @param array $parameters The parameters (name-value pairs) that will be extracted
     * and made available in the view file.
     *
     * @return DataResponse The response instance.
     */
    public function renderPartial(string $view, array $parameters = []): DataResponse
    {
        if ($this->layout === null) {
            return $this->render($view, $parameters);
        }

        return $this->withLayout(null)->render($view, $parameters);
    }

    /**
     * Renders a view as a string.
     *
     * @param string $view The view name {@see WebView::render()}.
     * @param array $parameters The parameters (name-value pairs) that will be extracted
     * and made available in the view file.
     *
     * @throws RuntimeException If the view cannot be resolved.
     * @throws Throwable If an error occurred during rendering.
     * @throws ViewNotFoundException If the view file does not exist.
     *
     * @return string The rendering result.
     */
    public function renderAsString(string $view, array $parameters = []): string
    {
        return $this->renderProxy(
            $view,
            $this->getContentParameters($parameters),
            $this->getLayoutParameters(),
            $this->getMetaTags(),
            $this->getLinkTags(),
        );
    }

    /**
     * Renders a view as string {@see renderAsString()} without applying a layout.
     *
     * @param string $view The view name {@see WebView::render()}.
     * @param array $parameters The parameters (name-value pairs) that will be extracted
     * and made available in the view file.
     *
     * @throws RuntimeException If the view cannot be resolved.
     * @throws Throwable If an error occurred during rendering.
     * @throws ViewNotFoundException If the view file does not exist.
     *
     * @return string The rendering result.
     */
    public function renderPartialAsString(string $view, array $parameters = []): string
    {
        if ($this->layout === null) {
            return $this->renderAsString($view, $parameters);
        }

        return $this->withLayout(null)->renderAsString($view, $parameters);
    }

    /**
     * Extracts the controller name and returns a new instance with the controller name.
     *
     * @param object $controller The controller instance.
     *
     * @return self
     */
    public function withController(object $controller): self
    {
        $new = clone $this;
        $new->name = $this->extractControllerName($controller);
        return $new;
    }

    /**
     * Returns a new instance with the specified controller name.
     *
     * @param string $name The controller name.
     *
     * @return self
     */
    public function withControllerName(string $name): self
    {
        $new = clone $this;
        $new->name = $name;
        return $new;
    }

    /**
     * Returns a new instance with the specified view path.
     *
     * @param string $viewPath The full path to the directory of views or its alias.
     *
     * @return self
     */
    public function withViewPath(string $viewPath): self
    {
        $new = clone $this;
        $new->viewPath = rtrim($viewPath, '/');
        return $new;
    }

    /**
     * Returns a new instance with the specified layout.
     *
     * @param string|null $layout The layout name (e.g. "layout/main") to be applied to views.
     * If null, the layout will not be applied.
     *
     * @return self
     */
    public function withLayout(?string $layout): self
    {
        $new = clone $this;
        $new->layout = $layout;
        return $new;
    }

    /**
     * Return a new instance with the appended specified injections.
     *
     * @param object ...$injections The injection instances.
     *
     * @return self
     */
    public function withAddedInjections(object ...$injections): self
    {
        $new = clone $this;
        $new->injections = array_merge($this->injections, $injections);
        return $new;
    }

    /**
     * Returns a new instance with the specified injections.
     *
     * @param object ...$injections The injection instances.
     *
     * @return self
     */
    public function withInjections(object ...$injections): self
    {
        $new = clone $this;
        $new->injections = $injections;
        return $new;
    }

    /**
     * Renders a view as a string injecting parameters and tags into view context.
     *
     * @param string $view The view name {@see WebView::render()}.
     * @param array $contentParameters The content parameters to inject.
     * @param array $layoutParameters The layout parameters to inject.
     * @param array $metaTags The meta tags to inject.
     * @param array $linkTags The link tags to inject.
     *
     * @throws RuntimeException If the view cannot be resolved.
     * @throws Throwable If an error occurred during rendering.
     * @throws ViewNotFoundException If the view file does not exist.
     *
     * @return string The rendering result.
     */
    private function renderProxy(
        string $view,
        array $contentParameters,
        array $layoutParameters,
        array $metaTags,
        array $linkTags
    ): string {
        $this->injectMetaTags($metaTags);
        $this->injectLinkTags($linkTags);

        $this->view = $this->view->withContext($this);
        $content = $this->view->render($view, $contentParameters);

        if ($this->layout === null) {
            return $content;
        }

        $layoutParameters['content'] = $content;
        $layout = $this->findLayoutFile($this->layout);

        return $this->view->renderFile($layout, $layoutParameters);
    }

    /**
     * Gets injection content parameters merged with parameters specified during rendering.
     *
     * The parameters specified during rendering have more priority and will
     * overwrite the injected content parameters if their names match.
     *
     * @param array $renderParameters Parameters specified during rendering.
     *
     * @return array The injection content parameters merged with the parameters specified during rendering.
     */
    private function getContentParameters(array $renderParameters): array
    {
        $parameters = [];
        foreach ($this->injections as $injection) {
            if ($injection instanceof ContentParametersInjectionInterface) {
                $parameters = array_merge($parameters, $injection->getContentParameters());
            }
        }
        return array_merge($parameters, $renderParameters);
    }

    /**
     * Gets the merged injection layout parameters.
     *
     * @return array The merged injection layout parameters.
     */
    private function getLayoutParameters(): array
    {
        $parameters = [];
        foreach ($this->injections as $injection) {
            if ($injection instanceof LayoutParametersInjectionInterface) {
                $parameters = array_merge($parameters, $injection->getLayoutParameters());
            }
        }
        return $parameters;
    }

    /**
     * Gets the merged injection meta tags.
     *
     * @return array The merged injection meta tags.
     */
    private function getMetaTags(): array
    {
        $tags = [];
        foreach ($this->injections as $injection) {
            if ($injection instanceof MetaTagsInjectionInterface) {
                $tags = array_merge($tags, $injection->getMetaTags());
            }
        }
        return $tags;
    }

    /**
     * Gets the merged injection link tags.
     *
     * @return array The merged injection link tags.
     */
    private function getLinkTags(): array
    {
        $tags = [];
        foreach ($this->injections as $injection) {
            if ($injection instanceof LinkTagsInjectionInterface) {
                $tags = array_merge($tags, $injection->getLinkTags());
            }
        }
        return $tags;
    }

    /**
     * Injects meta tags to the view.
     *
     * @param array $tags The meta tags to inject.
     *
     * @see WebView::registerMeta()
     * @see WebView::registerMetaTag()
     */
    private function injectMetaTags(array $tags): void
    {
        foreach ($tags as $key => $tag) {
            $key = is_string($key) ? $key : null;

            if (is_array($tag)) {
                $this->view->registerMeta($tag, $key);
                continue;
            }

            if (!($tag instanceof Meta)) {
                throw new InvalidMetaTagException(
                    sprintf(
                        'Meta tag in injection should be instance of %s or an array. Got %s.',
                        Meta::class,
                        $this->getType($tag),
                    ),
                    $tag
                );
            }

            $this->view->registerMetaTag($tag, $key);
        }
    }

    /**
     * Injects link tags to the view.
     *
     * @param array $tags The link tags to inject.
     *
     * @see WebView::registerLinkTag()
     */
    private function injectLinkTags(array $tags): void
    {
        foreach ($tags as $key => $tag) {
            if (is_array($tag)) {
                /** @var mixed */
                $position = $tag['__position'] ?? WebView::POSITION_HEAD;
                if (!is_int($position)) {
                    throw new InvalidLinkTagException(
                        sprintf(
                            'Link tag position in injection should be integer. Got %s.',
                            $this->getType($position),
                        ),
                        $tag
                    );
                }

                if (isset($tag[0]) && $tag[0] instanceof Link) {
                    $tag = $tag[0];
                } else {
                    unset($tag['__position']);
                    $tag = Html::link()->attributes($tag);
                }
            } else {
                $position = WebView::POSITION_HEAD;
                if (!($tag instanceof Link)) {
                    throw new InvalidLinkTagException(
                        sprintf(
                            'Link tag in injection should be instance of %s or an array. Got %s.',
                            Link::class,
                            $this->getType($tag),
                        ),
                        $tag
                    );
                }
            }

            $this->view->registerLinkTag($tag, $position, is_string($key) ? $key : null);
        }
    }

    /**
     * Finds a layout file based on the given file path or alias.
     *
     * @param string $file The file path or alias.
     *
     * @return string The path to the file with the file extension.
     */
    private function findLayoutFile(string $file): string
    {
        $file = $this->aliases->get($file);

        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }

        return $file . '.' . $this->view->getDefaultExtension();
    }

    /**
     * Returns a controller name based on controller instance.
     *
     * Name should be converted to "id" case without `controller` on the ending.
     *
     * If namespace does not contain `controller` or `controllers` then the method returns only classname without
     * `controller` at the end else it returns all sub-namespaces with `controller` (or `controllers`) at the end.
     *
     * @param object $controller The controller instance.
     *
     * @return string The controller name.
     *
     * @example App\Controller\FooBar\BazController -> foo-bar/baz
     * @example App\Controllers\FooBar\BazController -> foo-bar/baz
     * @example Path\To\File\BlogController -> blog
     *
     * @see Inflector::pascalCaseToId()
     */
    private function extractControllerName(object $controller): string
    {
        /** @var string[] $cache */
        static $cache = [];

        $class = get_class($controller);
        if (array_key_exists($class, $cache)) {
            return $cache[$class];
        }

        $regexp = '/((?<=controller\\\|s\\\)(?:[\w\\\]+)|(?:[a-z]+))controller/iuU';
        if (!preg_match($regexp, $class, $m) || empty($m[1])) {
            throw new RuntimeException('Cannot detect controller name.');
        }

        $inflector = new Inflector();
        $name = str_replace('\\', '/', $m[1]);
        return $cache[$class] = $inflector->pascalCaseToId($name);
    }

    /**
     * Returns the value type.
     *
     * @param mixed $value The value to check.
     *
     * @return string The value type.
     */
    private function getType($value): string
    {
        return is_object($value) ? get_class($value) : gettype($value);
    }
}
