<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Supervisord Monitoring</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link type="text/css" rel="stylesheet" href="<?php echo base_url('/css/bootstrap.min.css');?>"/>
	<link type="text/css" rel="stylesheet" href="<?php echo base_url('/css/bootstrap-responsive.min.css');?>"/>
	<link type="text/css" rel="stylesheet" href="<?php echo base_url('/css/custom.css');?>"/>
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
					<tr><th colspan="3">
						<a href="<?php echo htmlspecialchars($ui_url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></a> <?php if($this->config->item('show_host')){ ?><i><?php echo htmlspecialchars($parsed_url['host'], ENT_QUOTES, 'UTF-8'); ?></i><?php } ?>
						<?php
						if(isset($cfg[$name]['username'])){echo '<i class="icon-lock icon-green" style="color:blue" title="Authenticated server connection"></i>';}
						$server_version = is_array($version[$name]) ? 'Unavailable' : $version[$name];
						echo '&nbsp;<i>'.htmlspecialchars($server_version, ENT_QUOTES, 'UTF-8').'</i>';
						if(!isset($procs['error'])){
						?>
						<span class="server-btns pull-right">
							<?php echo monitor_control('stopall', $name, null, '<i class="icon-stop icon-white"></i> Stop all', 'btn btn-mini btn-inverse', count($procs)); ?>
							<?php echo monitor_control('startall', $name, null, '<i class="icon-play icon-white"></i> Start all', 'btn btn-mini btn-success'); ?>
							<?php echo monitor_control('restartall', $name, null, '<i class="icon icon-refresh icon-white"></i> Restart all', 'btn btn-mini btn-primary', count($procs)); ?>
						</span>
						<?php
						}
						?>
					</th></tr>
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
							$alert_button = '<a href="'.site_url('/control/clear/'.$name.'/'.$item_name).'" id="'.$name.'_'.$item_name.
									'" onclick="return false" data-toggle="popover" data-message="'.htmlspecialchars($check).'" data-original-title="'.
									$item_name.'@'.$name.'" class="pop btn btn-mini btn-danger process-alert"><img src="' . base_url('/img/alert_icon.png') . '" /></a>';
						}
						?>
						<tr class="process-row<?php echo ($status=='FATAL' ? ' process-fatal' : '');?>">
							<td class="process-name"><?php echo $item_name;?></td>
							<td class="process-state">
								<?php echo $alert_button;?>
								<span class="label label-<?php echo $class;?>"><?php echo $status;?></span>
								<?php if($log_note){ ?><small class="stderr-note"><?php echo $log_note; ?></small><?php } ?>
								<?php if($status=='RUNNING'){ ?><span class="process-uptime"><?php echo $uptime;?></span><?php } ?>
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
	<script>
	function show_content($param){
		stopTimer();
		$time = new Date();
		$message = $(this).data('message')+"\n"+$time+"\n"+$time.toDateString();
		$title = $(this).data('original-title');
		$popover_id = $(this).attr('id');
		$content = '<div class="well" style="padding:20px;">'+nl2br($message)+'</div>';
		<?php if($this->config->item('redmine_url') && strpos($this->config->item('redmine_url'), 'redmine.url') === false){ ?>
		$content+= '<div class="pull-left"><form method="get" action="<?php echo $this->config->item('redmine_url');?>" style="display:inline" target="_blank">';
		$content+= '<input type="hidden" name="issue[subject]" value="'+$title+'"/>';
		$content+= '<input type="hidden" name="issue[description]" value="'+$message+'"/>';
		$content+= '<input type="hidden" name="issue[assigned_to_id]" value="<?php echo $this->config->item('redmine_assigne_id');?>"/>';
		$content+= '<input type="submit" class="btn btn-small btn-inverse" value="Start New Ticket"/>';
		$content+= '</form></div>';
		<?php } ?>
		$content+= '<div class="pull-right"><a href="#" onclick="$(document.getElementById(\''+$popover_id+'\')).popover(\'hide\');startTimer();return false;" class="btn btn-small btn-primary">Fechar</a>&nbsp;&nbsp;';
		$content+= '<form class="monitor-control" method="post" action="'+$(this).attr('href')+'"><input type="hidden" name="monitor_csrf" value="'+<?php echo json_encode($CI->_csrf_token()); ?>+'"><button type="submit" class="btn btn-small btn-danger">Clear</button></form> &nbsp; </div>';
		return $content;
	}
		$('.pop').popover(
			{content: show_content,html:true,placement: 'bottom'}
		);
		var $refresh = parseInt(<?php echo $this->config->item('refresh');?>, 10);
		var $timer = null;
		var $refreshDeadline = null;
		$(window).load(function() {
			if($refresh > 0) {
				startTimer();
			}
		});
		$('.pop').on('hidden', function() {
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
	function nl2br (str, is_xhtml) {
		var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br ' + '/>' : '<br>';
		return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
	}
	</script>

</body>
</html>
