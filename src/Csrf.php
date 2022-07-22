<?php

declare(strict_types=1);

namespace Yiisoft\Yii\View;

use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Input;

final class Csrf
{
    private string $token;
    private string $parameterName;
    private string $headerName;

    public function __construct(string $token, string $parameterName, string $headerName)
    {
        $this->token = $token;
        $this->parameterName = $parameterName;
        $this->headerName = $headerName;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }

    public function hiddenInput(array $attributes = []): Input
    {
        $tag = Html::hiddenInput($this->parameterName, $this->token);
        return $attributes === [] ? $tag : $tag->addAttributes($attributes);
    }

    public function __toString(): string
    {
        return $this->getToken();
    }
}
