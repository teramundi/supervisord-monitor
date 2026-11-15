<?php
function monitor_control($action, $server, $worker, $label, $class, $count = null) {
    $ci =& get_instance();
    $path = 'control/' . $action . '/' . rawurlencode($server);
    if ($worker !== null) $path .= '/' . rawurlencode($worker);
    $description = ucfirst(str_replace('all', ' all processes', $action)) .
        ($worker !== null ? ' ' . $worker : '') . ' on ' . $server;
    $confirm = '';
    if (in_array($action, array('stopall', 'restartall'), true)) {
        $message = ($action === 'stopall' ? 'Stop' : 'Restart') . ' all ' . (int) $count . ' processes on ' . $server . '?';
        $confirm = ' data-confirm="' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '" onsubmit="return window.confirm(this.getAttribute(\'data-confirm\'));"';
    }
    return '<form' . $confirm . ' class="monitor-control" method="post" action="' . htmlspecialchars(site_url($path), ENT_QUOTES, 'UTF-8') . '">' .
        '<input type="hidden" name="monitor_csrf" value="' . htmlspecialchars($ci->_csrf_token(), ENT_QUOTES, 'UTF-8') . '">' .
        '<button aria-label="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" type="submit">' . $label . '</button></form>';
}

// A content fingerprint prevents stale CSS after deployments or proxy caching.
function monitor_asset($path) {
    $url = base_url($path);
    if (defined('FCPATH') && is_file(FCPATH . ltrim($path, '/'))) {
        $url .= '?v=' . substr(hash_file('sha256', FCPATH . ltrim($path, '/')), 0, 12);
    }
    return $url;
}
