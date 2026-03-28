<?php

declare(strict_types=1);

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/../views/' . $template . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(404);
            echo 'View não encontrada.';
            return;
        }

        require __DIR__ . '/../views/partials/header.php';
        require $viewFile;
        require __DIR__ . '/../views/partials/footer.php';
    }
}

