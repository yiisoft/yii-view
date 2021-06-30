<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
    </a>
    <h1 align="center">Yii View Extension</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii-view/v/stable.png)](https://packagist.org/packages/yiisoft/yii-view)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii-view/downloads.png)](https://packagist.org/packages/yiisoft/yii-view)
[![Build status](https://github.com/yiisoft/yii-view/workflows/build/badge.svg)](https://github.com/yiisoft/yii-view/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/yii-view/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/yii-view/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/yii-view/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/yii-view/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fyii-view%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/yii-view/master)
[![static analysis](https://github.com/yiisoft/yii-view/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/yii-view/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yiisoft/yii-view/coverage.svg)](https://shepherd.dev/github/yiisoft/yii-view)

The package is an extension of the [Yii View Rendering Library](https://github.com/yiisoft/view/).
This package adds additional functionality for the WEB environment and compatibility of use with
[PSR-7](https://www.php-fig.org/psr/psr-7/) interfaces.

## Requirements

- PHP 7.4 or higher.

## Installation

The package could be installed with composer:

```shell
composer require yiisoft/yii-view --prefer-dist
```

## General usage

The view renderer renders the view and places it in the `Psr\Http\Message\ResponseInterface` instance:

```php
/**
 * @var \Yiisoft\Aliases\Aliases $aliases
 * @var \Yiisoft\DataResponse\DataResponseFactoryInterface $dataResponseFactory
 * @var \Yiisoft\View\WebView $webView
 */

$viewRenderer = new \Yiisoft\Yii\View\ViewRenderer(
    $dataResponseFactory,
    $aliases,
    $webView,
    '/path/to/views', // The full path to the directory of views or its alias.
    'layouts/main', // Default is null, which means not to use the layout.
);

// Rendering the view with the layout.
$response = $viewRenderer->render('site/page', [
    'parameter-name' => 'parameter-value',
]);
```

If the layout was installed, but you need to render the view without the layout,
you can use the immutable setter `withLayout()`:

```php
$viewRenderer = $viewRenderer->withLayout(null);

// Rendering the view without the layout.
$response = $viewRenderer->render('site/page', [
    'parameter-name' => 'parameter-value',
]);
```

Or use the `renderPartial()` method, which will call `withLayout(null)`inside:

```php
// Rendering the view without the layout.
$response = $viewRenderer->renderPartial('site/page', [
    'parameter-name' => 'parameter-value',
]);
```

You can change the path to the directory of views in runtime as follows:

```php
$viewRenderer = $viewRenderer->withViewPath('/new/path/to/views');
```

You can specify the full path to the views directory or its alias. For more information about path aliases,
see the description of the [yiisoft/aliases](https://github.com/yiisoft/aliases) package.

### Use in the controller

If the view render is used in the controller, and the folder name matches the controller name, you can specify
the name or instance of the controller once. With this approach, you do not need to specify the directory name
when rendering the view in methods (actions), since it will be added automatically.

```php
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\ViewRenderer;

class SiteController
{
    private ViewRenderer $viewRenderer;

    public function __construct(ViewRenderer $viewRenderer)
    {
        // Specify the name of the controller:
        $this->viewRenderer = $viewRenderer->withControllerName('site');
        // or specify an instance of the controller:
        //$this->viewRenderer = $viewRenderer->withController($this);
    }

    public function index(): ResponseInterface
    {
        return $this->viewRenderer->render('index');
    }
    
    public function contact(): ResponseInterface
    {
        // Some actions.
        return $this->viewRenderer->render('contact', [
            'parameter-name' => 'parameter-value',
        ]);
    }
}
```

This is very convenient if there are many methods (actions) in the controller.

### Injection of additional data to the views

In addition to the parameters passed directly when rendering the view, you can pass general content and layout
parameters that will be available in all views. Parameters should be wrapped by interface implementations
for their intended purpose, it can be separate classes or one general class.

```php
use Yiisoft\Yii\View\ContentParametersInjectionInterface;
use Yiisoft\Yii\View\LayoutParametersInjectionInterface;

final class MyParametersInjection implements
    ContentParametersInjectionInterface,
    LayoutParametersInjectionInterface
{
    public function getContentParameters(): array
    {
        return [
            'content-parameter-name' => 'content-parameter-value',
        ];
    }
    
    public function getLayoutParameters(): array
    {
        return [
            'layout-parameter-name' => 'layout-parameter-value',
        ];
    }
}
```

Link tags and meta tags should be organized in the same way.

```php
use Yiisoft\Html\Html;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\LinkTagsInjectionInterface;
use Yiisoft\Yii\View\MetaTagsInjectionInterface;

final class MyTagsInjection implements
    LinkTagsInjectionInterface,
    MetaTagsInjectionInterface
{
    public function getLinkTags(): array
    {
        return [
            Html::link()->toCssFile('/main.css'),
            'favicon' => Html::link('/myicon.png', [
                'rel' => 'icon',
                'type' => 'image/png',
            ]),
            'themeCss' => [
                '__position' => WebView::POSITION_END,
                Html::link()->toCssFile('/theme.css'),
            ],
            'userCss' => [
                '__position' => WebView::POSITION_BEGIN,
                'rel' => 'stylesheet',
                'href' => '/user.css',
            ],
        ];
    }
    
    public function getMetaTags(): array
    {
        return [
            Html::meta()->name('http-equiv')->content('public'),
            'noindex' => Html::meta()->name('robots')->content('noindex'),
            [
                'name' => 'description',
                'content' => 'This website is about funny raccoons.',
            ],
            'keywords' => [
                'name' => 'keywords',
                'content' => 'yii,framework',
            ],
        ];
    }
}
```

You can pass instances of these classes as the sixth optional parameter to the constructor when
creating the view renderer, or use the `withInjections()` and `withAddedInjections` methods.

```php
$parameters = new MyParametersInjection();
$tags = new MyTagsInjection();

$viewRenderer = $viewRenderer->withInjections($parameters, $tags);
// Or add it to the already set ones:
$viewRenderer = $viewRenderer->withAddedInjections($parameters, $tags);
```

The parameters passed during rendering of the view are considered in priority
and will overwrite the injected content parameters if their keys match.

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## License

The Yii View Extension is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
