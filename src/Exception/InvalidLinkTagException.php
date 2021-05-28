<?php

declare(strict_types=1);

namespace Yiisoft\Yii\View\Exception;

use RuntimeException;
use Yiisoft\FriendlyException\FriendlyExceptionInterface;

final class InvalidLinkTagException extends RuntimeException implements FriendlyExceptionInterface
{
    /**
     * @var mixed
     */
    private $tag;

    /**
     * @param mixed $tag
     */
    public function __construct(string $message, $tag)
    {
        $this->tag = $tag;

        parent::__construct($message);
    }

    public function getName(): string
    {
        return 'Invalid link tag configuration in injection';
    }

    public function getSolution(): ?string
    {
        $solution = 'Got link tag:' . "\n" . var_export($this->tag, true);
        $solution .= <<<SOLUTION


In injection that implements `Yiisoft\Yii\View\LinkTagsInjectionInterface` defined link tags in the method `getLinkTags()`.

The link tag can be define in the following ways:

- as array of attributes: `['rel' => 'stylesheet', 'href' => '/user.css']`,
- as instance of `Yiisoft\Html\Tag\Link`: `Html::link()->toCssFile('/main.css')`.

Optionally:
 - use array format and set the position in a page via `__position`.
 - use string keys of array as identifies the link tag.

Example:

```php
public function getLinkTags(): array
{
	return [
		'favicon' => Html::link('/myicon.png', [
			'rel' => 'icon',
			'type' => 'image/png',
		]),
		'themeCss' => [
			'__position' => \Yiisoft\View\WebView::POSITION_END,
			Html::link()->toCssFile('/theme.css'),
		],
		'userCss' => [
			'__position' => \Yiisoft\View\WebView::POSITION_BEGIN,
			'rel' => 'stylesheet',
			'href' => '/user.css',
		],
	];
}
```
SOLUTION;
        return $solution;
    }
}
