<?php

namespace App\Core;

class View
{
    private static string $viewPath;
    public static array $shared = [];

    public static function init(string $path): void
    {
        self::$viewPath = $path;
    }

    /**
     * Şablonu layout içinde render eder. Layout şablon önekinden seçilir:
     *   book/*  -> layouts/book.php
     *   panel/* -> layouts/panel.php
     *   sys/*   -> layouts/sys.php
     * layout='none' ile layout atlanır.
     */
    public static function render(string $template, array $data = [], ?string $layout = null): void
    {
        $file = self::$viewPath . '/' . $template . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }
        if ($layout === null) {
            if (str_starts_with($template, 'book/'))       $layout = 'book';
            elseif (str_starts_with($template, 'panel/'))  $layout = 'panel';
            elseif (str_starts_with($template, 'sys/'))    $layout = 'sys';
            else                                            $layout = 'none';
        }

        extract(self::$shared, EXTR_SKIP);
        extract($data, EXTR_OVERWRITE);

        ob_start();
        require $file;
        $content = ob_get_clean();

        if ($layout === 'none') {
            echo $content;
            return;
        }
        $layoutFile = self::$viewPath . '/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            echo $content;
            return;
        }
        require $layoutFile;
    }

    public static function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
