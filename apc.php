<?php

// config
//----------------------------------------------------------------------
define('ADMIN', (
	$_SERVER['REMOTE_ADDR']=='127.0.0.1' 
	|| $_SERVER['REMOTE_ADDR']=='::1' 
	|| substr($_SERVER['REMOTE_ADDR'], 0, 8)=='192.168.' 
	|| substr($_SERVER['REMOTE_ADDR'], 0, 3)=='10.'
	));

define('VARLIST_MAX', 10);
define('MAX_PREVIEW_MEM', '12000');
define('MAX_PREVIEW', '255');
define('SHOW_PREVIEW', false); // previews count as a hit, modifying stats
define('GRID_MAX', 5000);
define('SEARCH', null);
//define('SEARCH', 'test');

$COLOR0='#6c8aad';
$COLOR1='#0d80bd';
$COLOR2='#dddddd';
$COLOR3='#e7e7e7';
$COLOR4='#0000ff';
$COLOR5='#c0c0c0';
$COLOR6='#088006';
$COLOR7='#d9d88c';
$COLOR8='#808000';
$COLOR9='#666666';

$COLORA='#ffffff';
$COLORB='#000000';
$COLORC='#333333';
$COLORD='#555555';
$COLORE='#777777';
$COLORF='#f7f7f7';
$COLORG='#888888';
$COLORH='#ee8000';
$COLORI='#eeeeee';

if(!function_exists('apc_cache_info') 
	&& !function_exists('apcu_cache_info')){
	echo '<center><h1>APC not found</h1></center>';
	return 1;
}
if(!defined('APCU_APC_FULL_BC')){
	$apcu=0; // APC
}elseif(APCU_APC_FULL_BC){
	$apcu=1; // APCu APC-compatibility
}else $apcu=2; // APCu
define('APCu', $apcu);

define('APC_VERSION',phpversion('apc'.(APCu?'u':null)));

if(APCu==2){
	define('NHITS','nhits');
	define('ATIME','atime');
	define('CTIME','ctime');
	define('DTIME','dtime');
	define('NSLOTS','nslots');
	define('NEXPUNGES','nexpunges');
	define('STIME','stime');
	define('NMISSES','nmisses');
	define('NINSERTS','ninserts');
	define('NENTRIES','nentries');
}else{
	define('NHITS','num_hits');
	define('ATIME','access_time');
	define('CTIME','creation_time');
	define('DTIME','deletion_time');
	define('NSLOTS','num_slots');
	define('NEXPUNGES','expunges');
	define('STIME','start_time');
	define('NMISSES','num_misses');
	define('NINSERTS','num_inserts');
	define('NENTRIES','num_entries');
}

// actions
//----------------------------------------------------------------------
if(ADMIN && isset($_GET['a'])){
	switch(strtolower($_GET['a'])){
		case 'clear': cache_clear(); break;
		case 'fill': fill(); break;
		case 'test': test(); break;
	}
}
$s=(empty($_GET['s']))?'hit':$_GET['s'];
switch($s){
	case 'new': $sort=ATIME; $reverse=true; break;
	case 'mod': $sort='mtime'; $reverse=true; break;
	case 'big': $sort='mem_size'; $reverse=true; break;
	case 'hit': $sort=NHITS; $reverse=true; break;
}
$search=(!ADMIN 
		|| empty($_GET['q']) 
		|| isset($_GET['q'][32]) 
		|| preg_match('/\//', $_GET['q']))
		? SEARCH
		: $_GET['q'];

define('INCLUDED',($_SERVER['SCRIPT_FILENAME']!=__FILE__));
define('SORT', $sort);
define('SORT_REVERSE', $reverse);

// functions
//----------------------------------------------------------------------
function test(){
	for($i=0; $i<rand(1000,2000); $i++)
		cache_store(($nvar='test_'.$i.($time=time())),str_repeat(uniqid(),rand(1,1000)));
	if(cache_exists('test_9000')) foreach($vars=cache_fetch('test_9000') as $var)
			cache_fetch($var);
	$vars[]=$nvar;
	cache_store('test_9000', $vars);
	if(rand(0,100)<30) cache_fetch('miss');
}
function fill($size=null){
	if(empty($size))
        $smaInfo = sma_info(true);
		$size=$smaInfo['avail_mem'];
	$chunk=100000;
	for($i=$chunk; $i<($size-$chunk)*.97; $i+=$chunk)
		cache_store('test_fill_'.$i, str_repeat(chr(rand(1,27)),$chunk));
}
function cache_clear(){
	$clear='apc'.(APCu?'u':null).'_clear_cache';
	switch(APCu){
		case '0': case '1': return $clear('user');
		case '2': return $clear();
	}
}
function cache_store($var, $val, $ttl=600){
	$store='apc'.(APCu?'u':null).'_store';
	return $store($var, $val, $ttl);
}
function cache_fetch($var){
	$fetch='apc'.(APCu?'u':null).'_fetch';
	return $fetch($var);
}
function cache_exists($var){
	$fetch='apc'.(APCu?'u':null).'_exists';
	return $fetch($var);
}
function cache_info(){
	$info='apc'.(APCu?'u':null).'_cache_info';
	switch(APCu){
		case '0': case '1': return $info('user', true);
		case '2': return $info(true);
	}
}
function sma_info($limited=null){
	return (APCu)?apcu_sma_info($limited):apc_sma_info($limited);
}
function duration($secs){ 
	if($secs<1){ $ago='now'; goto ret; }
	$calc=array(
		'y'=>$secs/31556926 % 12,
		'w'=>$secs/604800 	% 52,
		'd'=>$secs/86400 	% 7,
		'h'=>$secs/3600 	% 24,
		'm'=>$secs/60 		% 60,
		's'=>$secs 			% 60 
	);
	$ago='';
	foreach($calc as $unit=>$time)
		if($time) $ago.=''.$time.''.'<unit class="'.$unit.'">'.$unit.'</unit> ';
	ret: return '<span class="time">'.$ago.'</span>';
}  
function size($bytes){
	$unit=array('B','KB','MB','GB','TB','PB','EB');   
	return round($bytes/pow(1024,($f=floor(log($bytes,1023.9999))))).'<unit class="'.$unit[$f].'">'.$unit[$f].'</unit>';
}
function val($val, $var=null){
	return '<val class="'.$var.'">'.(is_numeric($val)?number_format($val):$val).'</val>'
	.($var?' <var class="'.$var.'">'.$var.'</var> ':null);
}
function percent($val){
	return $val.'<span class="percent">%</span>';
}
function bar($p1=50, $p2=50, $l1='Left', $l2='Right',$c1='blue',$c2='red'){
	$bar='<div class="bar">';
	$bar.=$l1;
	$bar.=' <span style="float:right">'.$l2.'</span>';
	$bar.='<br>';
	$bar.='<table width="100%" height="16" cellspacing="0" cellpadding="0"><tr>
		<td style="color: '.$c1.'" align="right" bgcolor="'.$c1.'" color="'.$c1.'" width="'.$p1.'%"><span style="display: none;">'.str_repeat('+',$p1).'</span></td>
		<td align="left" bgcolor="'.$c2.'" color="'.$c2.'" width="'.$p2.'%"><span style="display: none; color: '.$c2.'">'.str_repeat('-',$p2).'</span></td>
	</tr></table></div>';

	return $bar;
}
function q($var,$val){
	$q['s']=isset($_GET['s'])?$_GET['s']:null;
	$q['q']=isset($_GET['q'])?$_GET['q']:null;
	$q[$var]=$val;
	return '?'.http_build_query($q);
}

// CSS style
//----------------------------------------------------------------------
$style=<<<"h"
<style>
	#apcu, body{ max-width: 70em; font-size: 1em; padding: 0; margin: 0 auto; font-family: sans-serif; }
	#apcu .body{ margin: 0 auto; padding: .5em; }
	#apcu .header{ background: $COLOR9; margin: 0; }
	
	form{ margin: 0; padding: 0; }
	form input{ background: $COLORG; border: thin solid $COLORD; }
	form input:hover, form input:focus{ background: $COLORG; border-color: $COLOR5; }
	form input[type="submit"]{ background: $COLOR9; color: $COLORI; border: thin solid $COLORE; border-radius: .25em; }
	form input[type="submit"]:hover, form input[type="submit"]:focus{ background: $COLORG; }
	
	h1{ color: white; font-size: 2em; margin: 0; padding: .5em; font-family: "Arial Black"; display: block; float: left; } 
	h1 .version{ font-size: .5em; font-weight: normal; font-family: monospace; color: $COLORI; } 
	.header #tools{ margin: 0; padding: 1em 1em 1em 1.8em; background: $COLOR2; float: right; clear: both;  border-radius: 0 0 0 2em; }	
	.header #search{ float: right; margin: 1em 1em 1em 0em; padding: .5em; background: $COLORD; width: 11em; }
	.header #search input{ width: 100%; text-align: center; }
	
	#uptime{ background: $COLOR2; display: block; padding: .5em 1em .5em 1em; clear: left; }
	#total{ float: left; background: $COLOR3; padding: 1em; font-size: 2em; margin: 1em 0 1em .25em; } 
	#stats{ float:right; clear: right; background: $COLOR3; padding: 1em; margin: 2.5em -.5em 2em 0; }

	val{ color: $COLORB; font-weight: bold; font-size: 1em; font-family: "Consolas",monospace; }
		val.hit{ color: $COLOR6; }
		val.missed{ color: $COLOR7; }
		val.used{ color: $COLOR1; }
		val.free{ color: $COLOR5; }
	var{ color: $COLORD; vertical-align: top; font-style: normal; padding: .25em; }
		var.hit{ background: $COLOR6; color: $COLORA; }
		var.missed{ background: $COLOR7; color: $COLORC; }
		var.used{ background: $COLOR1; color: $COLORA; }
		var.free{ background: $COLOR5; color: $COLORC; }
	unit{ font-weight: bold; color: brown; font-size: .7em; vertical-align: middle; padding: .14em; } 
		unit.h{ color: navy; }
		unit.m{ color: green; }
		unit.s{ color: $COLORH; }
		unit.GB{ color: red; }
		unit.MB{ color: $COLORH; }
		unit.KB{ color: green; }
		unit.B{ color: olive; }
	.percent{ color: $COLORD; font-size: .7em; font-weight: normal; vertical-align: super; }

	.bar{ margin: 2em 0 0 0; clear: both; }
	.bar table{ height: 4em; }
	.bar var{ line-height: 1.69em; }
		
	.graph{ display: block; background: $COLOR2; font-size: .5em; font-family: monospace; } 
	.graph span{ display:inline-block; width: 1em; height: 1em; border: thin dotted $COLORA; border-bottom: none; } 
	.graph .used{ background: $COLOR1; color: $COLOR1; } 
	.graph .full{ background: $COLOR0; color: $COLOR0; }
	.graph .free{ background: $COLOR2; color: $COLOR2; } 
	
	table.vars{ font-family: monospace; margin: 0; padding: 0; width: 100%; border: none; } 
	table.vars{ border-collapse: collapse; table-layout: fixed; } 
	table.vars .top th{ background: $COLORF; color: $COLORE; padding: 0em; font-family: sans-serif; font-weight: normal; }
	table.vars .top th a{ text-decoration: none; color: $COLORE; display: block; padding: .5em 1em .5em 1em;  }
	table.vars .top th.selected{ background: $COLOR3; }
	table.vars .top th.selected a{ color: $COLORC; }
	table.vars .top th a:hover, table.vars .top th a:focus{ color: $COLORB; background: $COLOR3; }
	table.vars th{ background: $COLOR3; color: $COLOR1; padding: 1em; font-family: sans-serif; font-weight: bold; } 
	table.vars tr{ border-bottom: thin dotted $COLOR5; } 
	table.vars td{ padding: .5em 2em .5em 1em; } 
	table.vars val{ font-weight: normal; } 
	.wrap{ text-overflow: ellipsis; overflow: hidden; whitespace: nowrap; word-wrap: break-word; overflow-wrap: break-word; }
	
	input:focus, a:focus{ outline: none; }
	input::-moz-focus-inner{ border: none; }
</style>
h;

// HTML head
//----------------------------------------------------------------------
$title='APC'.(APCu?'u':null);
if(!INCLUDED) echo <<<"h"
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<title>$title</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<meta name="viewport" content="width=device-width; initial-scale=1.0">
	$style
</head>
<body>
h;
else echo $style;
echo '<div id="apcu" class="module">';

// load APC info
//----------------------------------------------------------------------
$info=cache_info();
$mem=sma_info();

// header
//----------------------------------------------------------------------
echo '<div class="header">';
echo '<h1>'.$title.' <span class="version">v'.APC_VERSION.'</span></h1>';

if(ADMIN){
	echo '<form id="search" method="get">
	<input type="text" name="q" value="'.(isset($_GET['q'])?htmlspecialchars($_GET['q']):SEARCH).'" maxlength="32" placeholder="search">
	'.(isset($_GET['s'])?'<input type="hidden" name="s" value="'.htmlspecialchars($_GET['s']).'">':null).'
	</form>';

	echo '<div id="tools">
	<form method="get">
		<input type="submit" name="a" value="Clear"> 
		<input type="submit" name="a" value="Fill">
		<input type="submit" name="a" value="Test"> 
	</form> 
	</div>';
}

echo '<span id="uptime">Up '.val(duration(time()-$info[STIME])).'</span>';
echo '</div>';

echo '<div class="body">';

// total cache
//----------------------------------------------------------------------
$total=$mem['num_seg']*$mem['seg_size'];
$free=$mem['avail_mem'];
$used=$total-$free;
echo '<p id="total">';
echo val(size($total), 'pool');
echo '</p>';

// global stats
//----------------------------------------------------------------------
echo '<p id="stats">';
foreach(array(
//'mem_size'=>'memory',
//NSLOTS=>'slots',
NINSERTS=>'inserts', 
NENTRIES=>'cached',
NEXPUNGES=>'expunges',
//NHITS=>'hits',
//NMISSES=>'misses',
//'ttl'=>'expire',
        ) as $key=>$label)
	echo val($info[$key], $label);
echo '</p>';

// hits and misses
//----------------------------------------------------------------------
$totalHits=$info[NHITS]+$info[NMISSES];
$missP=($totalHits)?round($info[NMISSES]/$totalHits*100):0;
$hitP=100-$missP;
$hitL=val(percent($hitP), 'hit').' ('.val($info[NHITS]).')';
$missL=val(percent($missP), 'missed').' ('.val($info[NMISSES]).')';

echo bar($hitP,$missP,$hitL,$missL,$COLOR6,$COLOR7);

// percentage used/free bar
//----------------------------------------------------------------------
$usedP=round($used/$total*100);
$freeP=round($free/$total*100);
$usedL=val(percent($usedP), 'used').' ('.val(size($used)).')';
$freeL=val(percent($freeP),'free').' ('.val(size($free)).')';
echo bar($usedP, $freeP, $usedL, $freeL, $COLOR1, $COLOR5);

// memory graph
//----------------------------------------------------------------------
$grids=($info[NSLOTS]<GRID_MAX)?$info[NSLOTS]:GRID_MAX;
//echo val(size($blocksize=$total/$grids), 'blocksize');

$space=array();
foreach($mem['block_lists'] as &$seg){
	foreach($seg as $key=>&$block){
		$start=$block['offset']/$total*$grids;
		$startFloor=floor($start);
		$startCeil=ceil($start);
		
		$end=($block['offset']+$block['size'])/$total*$grids;
		$endFloor=floor($end);

		if($end-$start<=1){
			if(!isset($space[$startFloor])) $space[$startFloor]=1;
			$hole=($end-$start);
			$space[$startFloor]-=$hole;
		}else{
			for($s=$startCeil; $s<$endFloor; $s++)
				if(!isset($space[$s])) $space[$s]=0;

			if(!isset($space[$startFloor])) $space[$startFloor]=1;
			if(!isset($space[$endFloor])) $space[$endFloor]=1;

			$space[$startFloor]-=($startCeil-$start);
			$space[$endFloor]-=($end-$endFloor);
		}
		unset($seg[$key]);
	}
}

unset($start, $size);
$filled=0;
echo '<span class="graph">';
for($x=0; $x<$grids; $x++){
	$fill=(isset($space[$x]) && $space[$x]==0)?'-':'+';
	$class=(isset($space[$x]) && $space[$x]==0)?'free':'used';
	$alpha=(isset($space[$x]) && $space[$x]>0)?'style="opacity:'.round($space[$x],2).'"':null;
	echo '<span '.$alpha.' class="'.$class.'"> <span style="display:none">'.$fill.'</span> </span>';
	unset($space[$x]);
}
echo '</span>';

// cached variable table
//----------------------------------------------------------------------
function csort($a, $b){
	return $a[SORT]<$b[SORT];
}
$apc=new APCIterator('user','/'.$search.'/',APC_ITER_KEY|APC_ITER_MEM_SIZE|APC_ITER_MTIME|APC_ITER_ATIME|APC_ITER_NUM_HITS, null);

$c=0;
$now=time();
foreach($apc as $var){
	if($var[ATIME]==$var['mtime']) $var[ATIME]=0;
	$vars[]=$var;
	$c++;
	if($c>VARLIST_MAX) break;
}
if(empty($vars)) goto end;
usort($vars, 'csort');    
foreach($apc as $var){
	if($c){ $c--; continue; }
	end($vars);
	if($var[ATIME]==$var['mtime']) $var[ATIME]=0;
    $currentVars = current($vars);
	if($var[SORT]>$currentVars[SORT]){
		array_pop($vars);
		$vars[]=$var;
		usort($vars, 'csort');
	}
}
if(SHOW_PREVIEW) foreach($vars as &$var){
	if($var['mem_size']>MAX_PREVIEW_MEM) continue;
	$temp=cache_fetch($var['key']);
	if(is_array($temp)){
		if(isset($temp[0])) $temp=$temp[0];
		$temp=implode(' ',$temp);
	}
	$temp=mb_substr($temp, 0, MAX_PREVIEW);
	if(!preg_match('/\s/',$temp)) $temp=chunk_split($temp, 6, ' ');
	$var['preview']=$temp;
	unset($temp);
}
unset($apc);
usort($vars, 'csort');

echo '<table class="vars" width="100%">';
$selected=' class="selected"';
echo '<tr class="top"><th>name</th>';
if(SHOW_PREVIEW) echo '<th>preview</th>';
echo '<th width="10%" align="right"'.(SORT!='mem_size'?:$selected).'><a href="'.q('s','big').'">size</a></th>
	<th width="10%" align="right"'.(SORT!=NHITS?:$selected).'><a href="'.q('s','hit').'">hits</a></th>
	<th width="15%" align="right"'.(SORT!=ATIME?:$selected).'><a href="'.q('s','new').'">viewed</a></th>
	<th width="15%" align="right"'.(SORT!='mtime'?:$selected).'><a href="'.q('s','mod').'">changed</a></th>
	</tr>';

$cur=0;
foreach($vars as $var){
	$hitP=($info[NHITS])?round($var[NHITS]/$info[NHITS]*100):0;
	echo '<tr>';
		echo '<th align="left" class="wrap">'.$var['key'].'</th> ';
		if(SHOW_PREVIEW) echo '<td align="left" class="wrap">'.(isset($var['preview'])?$var['preview']:null).'</td> ';
		echo '<td align="right">'.val(size($var['mem_size'])).'</td>';	
		echo '<td align="right">'.val($var[NHITS]).'</td>';
		echo '<td align="right">'.($var[ATIME]?val(duration(time()-$var[ATIME])):'never').'</td>';
		echo '<td align="right">'.val(duration(time()-$var['mtime'])).'</td>';
	echo '</tr>';

	$p=0;
	if(SORT=='mem_size'){
		if($var['mem_size']){
			$p=round($var['mem_size']/$used*100);
			$label='size';
		}
	}elseif(SORT=='nhits'){
		if($var[NHITS]){
			$p=round($var[NHITS]/$info[NHITS]*100);
			$label='hits';
		}
	}
	
	if($p){
		echo '<tr bgcolor="#ddd">
			<td align="right">'.val(percent($p), $label).'</td>
			<td colspan="'.(SHOW_PREVIEW?5:4).'" style="padding:0">
				<table width="100%"><tr>
					<td style="padding:0" width="'.$p.'%" bgcolor="teal">&nbsp;</td>
					<td style="padding:0" width="'.(100-$p).'%"></td>
				</tr></table>
			</td></tr>';
	}

	$cur++;
	if($cur==VARLIST_MAX) break;
}
echo '</table>';

// HTML body
//----------------------------------------------------------------------
end:
echo '<p style="text-align:right">';
echo val(round(microtime(true)-$_SERVER["REQUEST_TIME_FLOAT"],4).'<unit class="s">s</unit>', 'render');
echo ' '.val(size(memory_get_peak_usage()), 'mem');
echo '</p>';
if(!INCLUDED) echo '</div></div></body></html>';
