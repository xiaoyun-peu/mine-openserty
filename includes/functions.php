<?php
/**
 * 公共函数
 */

if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(string $scope = 'default'): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['_csrf'][$scope]) || !is_string($_SESSION['_csrf'][$scope])) {
            $_SESSION['_csrf'][$scope] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'][$scope];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(string $scope = 'default'): void {
        echo '<input type="hidden" name="_csrf" value="' . e(csrf_token($scope)) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(string $scope = 'default', ?string $token = null): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $token ?? ($_POST['_csrf'] ?? '');
        $stored = $_SESSION['_csrf'][$scope] ?? '';
        return is_string($token) && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
    }
}

/**
 * 输出导航链接，当前页自动高亮
 */
function nav_link($href, $label, $page) {
    // href 形如 index.php / info.php#rules
    $file = strtok($href, '#');
    $active = ($file === $page) ? ' class="active"' : '';
    echo '<li><a href="' . e($href) . '"' . $active . '>' . e($label) . '</a></li>';
}
