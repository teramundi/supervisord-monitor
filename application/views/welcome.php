<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Supervisord Monitoring</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link type="text/css" rel="stylesheet" href="<?php echo base_url('/css/bootstrap.min.css');?>"/>
	<link type="text/css" rel="stylesheet" href="<?php echo base_url('/css/bootstrap-responsive.min.css');?>"/>
	<link type="text/css" rel="stylesheet" href="<?php echo monitor_asset('/css/custom.css');?>"/>
<script type="text/javascript" src="<?php echo base_url('/js/jquery-1.10.1.min.js');?>"></script>
<script type="text/javascript" src="<?php echo base_url('/js/bootstrap.min.js');?>"></script>
	<noscript>
	<?php
	if($this->config->item('refresh')){ ?>
	<meta http-equiv="refresh" content="<?php echo $this->config->item('refresh');?>">
	<?php } ?>
	</noscript>
</head>
<body>
	<div class="navbar navbar-inverse navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
		  <a class="brand" href="<?php echo site_url('');?>">Supervisor Monitor</a>
		  <div class="nav-collapse collapse">
			<ul class="nav">
			<li class="active"><a href="<?php echo site_url();?>">Home</a></li>
			<?php if($this->config->item('enable_alarm')){ ?>
              <li><a href="?mute=<?php echo ($muted?-1:1);?>"><i class="icon-music icon-white"></i>&nbsp;<?php
			if($muted){
				echo "Unmute";
			}else{
				echo "Mute";
			}
		;?></a></li>
			<?php } ?>
		<li><a href="<?php echo site_url();?>">Refresh <b id="refresh">(<?php echo $this->config->item('refresh');?>)</b> &nbsp;</a></li>
			</ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>
	

	<div class="container">
        <?php if (!empty($notice)) { ?>
        <div role="status" class="alert alert-<?php echo $notice['type'] === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($notice['message'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>
		<?php
		if($muted && $this->config->item('enable_alarm')){
			echo '<div class="row"><div class="span4 offset4 label label-important" style="padding:10px;margin-bottom:20px;text-align:center;">';
			echo 'Sound muted for '.timespan(time(),$muted).' <span class="pull-right"><a href="?mute=-1" style="color:white;"><i class="icon-music icon-white"></i> Unmute</a></span></div></div>';
		}
	
		?>
		<div class="row">
				<?php
				$alert = false;
				foreach($list as $name=>$procs){
					$parsed_url = parse_url($cfg[$name]['url']);
					// Never expose RPC credentials in the link to Supervisor's web UI.
					$scheme = isset($parsed_url['scheme']) && $parsed_url['scheme'] === 'https' ? 'https' : 'http';
					$ui_url = $scheme . '://' . $parsed_url['host'] . ':' . $cfg[$name]['port'] . '/';
				?>
				<div class="span<?php echo ($this->config->item('supervisor_cols')==2?'6':'4');?>">
					<table class="table table-bordered table-condensed table-striped monitor-table">
						<colgroup>
							<col class="col-process" />
							<col class="col-state" />
							<col class="col-actions" />
						</colgroup>
					<tr><th colspan="3"><div class="server-header"><div class="server-identity">
						<a href="<?php echo htmlspecialchars($ui_url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></a> <?php if($this->config->item('show_host')){ ?><i><?php echo htmlspecialchars($parsed_url['host'], ENT_QUOTES, 'UTF-8'); ?></i><?php } ?>
						<?php
						if(isset($cfg[$name]['username'])){echo '<i class="icon-lock icon-green" style="color:blue" title="Authenticated server connection"></i>';}
						$server_version = is_array($version[$name]) ? 'Unavailable' : $version[$name];
						echo '&nbsp;<i>'.htmlspecialchars($server_version, ENT_QUOTES, 'UTF-8').'</i>';
						echo '</div>';
						if(!isset($procs['error'])){
						?>
						<div class="server-btns">
							<?php echo monitor_control('stopall', $name, null, '<i class="icon-stop icon-white"></i> Stop all', 'btn btn-mini btn-inverse', count($procs)); ?>
							<?php echo monitor_control('startall', $name, null, '<i class="icon-play icon-white"></i> Start all', 'btn btn-mini btn-success'); ?>
							<?php echo monitor_control('restartall', $name, null, '<i class="icon icon-refresh icon-white"></i> Restart all', 'btn btn-mini btn-primary', count($procs)); ?>
						</div>
						<?php
						}
						?>
					</div></th></tr>
					<?php
					$CI = &get_instance();
					if (isset($procs['error'])) {
						echo '<tr><td colspan="3">'.htmlspecialchars($procs['error'], ENT_QUOTES, 'UTF-8').'</td></tr>';
						echo '<tr><td colspan="3">For troubleshooting <a href="https://github.com/mlazarov/supervisord-monitor#troubleshooting" target="_blank">check this guide</a></td></tr>';
					}
					foreach((isset($procs['error']) ? array() : $procs) as $item){
						if (!is_array($item) || !isset($item['group'], $item['name'], $item['statename'])) {
							echo '<tr><td colspan="3">Invalid process information returned by Supervisor.</td></tr>';
							continue;
						}

						if($item['group'] != $item['name']) $item_name = $item['group'].":".$item['name'];
						else $item_name = $item['name'];
						
						$check = $CI->_request($name,'readProcessStderrLog',array($item_name,-1000,0));
						$log_note = '';
						// NO_FILE can mean redirected stderr, disabled logging, or a missing file.
						if(is_array($check) && isset($check['error']) && preg_match('/^NO_FILE(?:\s*:|$)/', $check['error'])){
							$log_note = 'No stderr log available';
							$check = '';
						}
						elseif(is_array($check)) $check = print_r($check,1);
						
						$pid = $uptime = '&nbsp;';
						$status = $item['statename'];
						if($status=='RUNNING'){
							$class = 'success';
							list($pid,$uptime) = explode(",",$item['description']);
						}
						elseif($status=='STARTING') $class = 'info';
						elseif($status=='FATAL') { $class = 'important'; $alert = true; }
						elseif($status=='STOPPED') $class = 'inverse';
						else $class = 'error';

						$uptime = str_replace("uptime ","",$uptime);
						$alert_button = '';
						if($check){
							$alert = true;
							$alert_button = '<a href="'.htmlspecialchars(site_url('/control/clear/'.rawurlencode($name).'/'.rawurlencode($item_name)), ENT_QUOTES, 'UTF-8').'" id="'.htmlspecialchars($name.'_'.$item_name, ENT_QUOTES, 'UTF-8').
									'" onclick="return false" data-toggle="process-log" data-message="'.htmlspecialchars($check, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" data-original-title="'.
									htmlspecialchars($item_name.'@'.$name, ENT_QUOTES, 'UTF-8').'" aria-label="View process log" class="pop btn btn-mini btn-danger process-alert"><img src="' . base_url('/img/alert_icon.png') . '" /></a>';
						}
						?>
						<tr class="process-row<?php echo ($status=='FATAL' ? ' process-fatal' : '');?>">
							<td class="process-name"><?php echo htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8');?></td>
							<td class="process-state">
								<?php echo $alert_button;?>
								<span class="label label-<?php echo $class;?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8');?></span>
								<?php if($log_note){ ?><small class="stderr-note"><?php echo $log_note; ?></small><?php } ?>
								<?php if($status=='RUNNING'){ ?><span class="process-uptime"><?php echo htmlspecialchars($uptime, ENT_QUOTES, 'UTF-8');?></span><?php } ?>
							</td>
							<td class="process-actions">
								<!--div class="btn-group">
									<button class="btn btn-mini">Action</button>
									<button class="btn btn-mini dropdown-toggle" data-toggle="dropdown">
										<span class="caret"></span>
									</button>
									<ul class="dropdown-menu">
										<li><a href="test">Restart</a></li>
										<li><a href="zz">Stop</a></li>
									</ul>
								</div//-->
								<div class="actions">
									<?php if($status=='RUNNING'){ ?>
									<?php echo monitor_control('stop', $name, $item_name, '<i class="icon-stop icon-white"></i>', 'btn btn-mini btn-inverse'); ?>
									<?php echo monitor_control('restart', $name, $item_name, '<i class="icon-refresh icon-white"></i>', 'btn btn-mini btn-inverse'); ?>
									<?php } if($status=='STOPPED' || $status == 'EXITED' || $status=='FATAL'){ ?>
									<?php echo monitor_control('start', $name, $item_name, '<i class="icon-play icon-white"></i>', 'btn btn-mini btn-success'); ?>
									<?php } ?>
								</div>
							</td>
						</tr>
						<?php
					}

					?>
				</table>				
			</div>
				<?php
				}
				if($alert && !$muted && $this->config->item('enable_alarm')){
					echo '<embed height="0" width="0" src="'.base_url('/sounds/alert.mp3').'">';
				}
				if($alert){
					echo '<title>!!! WARNING !!!</title>';
				}else{
				echo '<title>Supervisor Monitor</title>';
				}
				
				?>
		</div>
	</div>

    </div> <!-- /container -->
	
	<div class="footer">
		<p>Powered by <a href="https://github.com/mlazarov/supervisord-monitor" target="_blank">Supervisord Monitor</a> | Page rendered in <strong>{elapsed_time}</strong> seconds</p>
	</div>
    <div id="log-dialog" class="modal hide fade monitor-log-dialog" tabindex="-1" role="dialog" aria-labelledby="log-dialog-title" aria-hidden="true">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            <h3 id="log-dialog-title">Process log</h3>
        </div>
        <div class="modal-body"></div>
        <div class="modal-footer monitor-log-actions"></div>
    </div>
	<script>
    function show_content() {
        stopTimer();
        var trigger = $(this);
        var dialog = $('#log-dialog');
        var content = dialog.find('.modal-body').empty();
        var actions = dialog.find('.modal-footer').empty();
        dialog.find('#log-dialog-title').text(trigger.attr('data-original-title'));
        $('<p>').addClass('log-description').text('Latest stderr output. Clear logs removes both stdout and stderr logs for this process.').appendTo(content);
        $('<pre>').addClass('well monitor-log').text(String(trigger.attr('data-message') || '')).appendTo(content);
        <?php if($this->config->item('redmine_url') && strpos($this->config->item('redmine_url'), 'redmine.url') === false){ ?>
        var ticket = $('<form>').attr({method: 'get', target: '_blank', action: <?php echo json_encode($this->config->item('redmine_url'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>}).appendTo(actions);
        $('<input>').attr({type:'hidden', name:'issue[subject]'}).val(trigger.attr('data-original-title')).appendTo(ticket);
        $('<input>').attr({type:'hidden', name:'issue[description]'}).val(trigger.attr('data-message')).appendTo(ticket);
        $('<input>').attr({type:'hidden', name:'issue[assigned_to_id]'}).val(<?php echo json_encode($this->config->item('redmine_assigne_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>).appendTo(ticket);
        $('<button>').attr('type','submit').addClass('btn btn-small').text('Start New Ticket').appendTo(ticket);
        <?php } ?>
        $('<button>').attr({type:'button', 'data-dismiss':'modal'}).addClass('btn').text('Close').appendTo(actions);
        var clear = $('<form>').addClass('monitor-control').attr({method:'post', action:trigger.attr('href')}).on('submit', function(){
            return window.confirm('Clear stdout and stderr logs for ' + trigger.attr('data-original-title') + '? This cannot be undone.');
        }).appendTo(actions);
        $('<input>').attr({type:'hidden', name:'monitor_csrf'}).val(<?php echo json_encode($CI->_csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>).appendTo(clear);
        $('<button>').attr('type','submit').addClass('btn btn-danger').text('Clear logs').appendTo(clear);
        dialog.modal('show');
    }
    $('.pop').on('click', function(event){ event.preventDefault(); show_content.call(this); });

		var $refresh = parseInt(<?php echo $this->config->item('refresh');?>, 10);
		var $timer = null;
		var $refreshDeadline = null;
		$(window).load(function() {
			if($refresh > 0) {
				startTimer();
			}
		});
		$('#log-dialog').on('hidden', function() {
			if($refresh > 0 && !$timer) {
				startTimer();
			}
		});
		function stopTimer(showPaused){
			if($refreshDeadline !== null) {
				$refresh = Math.max(0, Math.ceil(($refreshDeadline - new Date().getTime()) / 1000));
			}
			if($timer !== null) {
				clearTimeout($timer);
				$timer = null;
			}
			$refreshDeadline = null;
			if(showPaused !== false) {
				$('#refresh').html('(paused)');
			}
		}
		function startTimer(){
			if($refresh <= 0) return;
			stopTimer(false);
			$refreshDeadline = new Date().getTime() + ($refresh * 1000);
			timer();
		}
		function timer(){
			$refresh = Math.max(0, Math.ceil(($refreshDeadline - new Date().getTime()) / 1000));
			$('#refresh').html('('+$refresh+')');
			if($refresh<=0){
				$timer = null;
				$refreshDeadline = null;
				location.href="<?php echo site_url() ?>";
				return;
			}
			$timer = setTimeout(timer,250);
		}

	</script>

</body>
</html>
