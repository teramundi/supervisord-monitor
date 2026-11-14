<?php
function monitor_control($action, $server, $worker, $label, $class, $count = null) {
    $ci =& get_instance();
    $path = 'control/' . $action . '/' . rawurlencode($server);
    if ($worker !== null) $path .= '/' . rawurlencode($worker);
    $confirm = '';
    if (in_array($action, array('stopall', 'restartall'), true)) {
        $message = ($action === 'stopall' ? 'Stop' : 'Restart') . ' all ' . (int) $count . ' processes on ' . $server . '?';
        $confirm = ' data-confirm="' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '" onsubmit="return window.confirm(this.getAttribute(\'data-confirm\'));"';
    }
    return '<form' . $confirm . ' class="monitor-control" method="post" action="' . htmlspecialchars(site_url($path), ENT_QUOTES, 'UTF-8') . '">' .
        '<input type="hidden" name="monitor_csrf" value="' . htmlspecialchars($ci->_csrf_token(), ENT_QUOTES, 'UTF-8') . '">' .
        '<button class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" type="submit">' . $label . '</button></form>';
}
