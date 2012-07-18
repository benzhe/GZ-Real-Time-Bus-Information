<?php
  date_default_timezone_set('Asia/Shanghai');
  ob_start("ob_gzhandler");
  // Just For UC Broswer;
  if(isset($_GET['cookie'])) {
    setcookie('bus',$_GET['cookie'], time()+2*3600);
    header("Content-type: text/javascript");
    die (";");
  }
  if(preg_match('/Java/',$_SERVER['HTTP_USER_AGENT'])) $isJava = True;
  if(preg_match('/UC/',$_SERVER['HTTP_USER_AGENT'])) $isUC = True;
  if(preg_match('/JUC/',$_SERVER['HTTP_USER_AGENT'])) $isJUC = True;
  if(preg_match('/MSIE/',$_SERVER['HTTP_USER_AGENT'])) $isIE = True;
  if(preg_match('/iPhone|iPod|iTouch|iPad/isU',$_SERVER['HTTP_USER_AGENT'])) $isIOS = True;
  //echo $_SERVER['HTTP_USER_AGENT'];
  require 'config.php'; //load API url 因为此接口原非公开，所以暂不公布;
  $debug = True;
  $api_time = time();
  $m_err = 0; //校正时间误差，API 服务器时间居然比标准时间慢 30 - 37 秒；
  function err($code) {
       if($GLOBALS['debug']){
         $mysql = new SaeMysql();
	$sql = "INSERT  INTO `result` ( `status`) VALUES ( ".$code." ) ";
	$mysql->runSql( $sql );
	$mysql->closeDb(); 
        
        }
        if(intval($code)<=-100){
          echo '<p style="padding-top:20px;">0_o 接口不稳定，<span id="timer">15秒后自动</span><a href="javascript:void(0)" onclick="clearInterval(m);location.reload()">刷新</a></p>';
          echo "<script>var timer = document.getElementById('timer');  var i = 15; var m = setInterval(function(){timer.innerHTML=i+'秒后自动';--i;if(i==0){clearInterval(m);location.reload();}},1000);</script>";            
        }
        else {
          echo '<p style="padding-top:20px;">0_o 输入有误，请重新输入。<br>线路格式：“b22、B25路、801、高峰4线、大学城4线、夜4”；<br>站点格式：“华师站、华景新城、体育中心站B3”</p>';
        }
        die('<style>form{padding-top:60px}</style></body></html>');
        
  }
//  $opts = array( 
//    'http'=>array( 
//    'method'=>'GET', 
//    'timeout'=>3
//  )); 
//  $context = stream_context_create($opts);
  
  //sae 的 fetchurl 不支持 proxy , 只能用此下策
  function proxy_url($proxy_url, $count) {
    require 'config.php';
    $proxy_name = $proxys[$count][0];
    $proxy_port = $proxys[$count][1];
    $proxy_cont = '';
    $proxy_fp = fsockopen($proxy_name, $proxy_port, $errno, $errstr, 1);
    if (!$proxy_fp) { return false; }
    fputs($proxy_fp, "GET $proxy_url HTTP/1.0\r\n\r\n");
    stream_set_blocking($proxy_fp, TRUE); 
    stream_set_timeout($proxy_fp, 1);
    $info = stream_get_meta_data($proxy_fp); 
    while(!feof($proxy_fp) && (!$info['timed_out']) ) { $info = stream_get_meta_data($proxy_fp);$proxy_cont .= fread($proxy_fp,4096); }
    fclose($proxy_fp);
    preg_match('/Date:(.*)\r\n/isU',$proxy_cont,$matches);
    if($matches) $GLOBALS['api_time'] = strtotime($matches[1]); //使用 API 服务器时间，貌似他的时间跟 GPS 时间不一样啊
    $proxy_cont = substr($proxy_cont, strpos($proxy_cont,"\r\n\r\n")+4);
    return $proxy_cont;
  }
  
  function getinfo($act, $str) {
    require 'config.php';
    $str = iconv('utf-8','gb2312',strtoupper($str));
    switch ($act) {
      case 'search':
        $url = $search_url . $str; 
        break;
      case 'detail':
        $url = $detail_url . $str;
        break;
      case 'find':
        $url = $find_url .$str;
        break;
      case 'station':
        $url = $station_url .$str;
        break;
    }
    //$raw = @file_get_contents($url,false,$context);
    $raw = 0;
    if(!$raw || !check($raw)) {
      shuffle($proxys);
      foreach($proxys as $k => $v) {
        $raw = @proxy_url($url, $k);
        if($raw && check($raw)) {
          if($GLOBALS['debug']){$mysql = new SaeMysql();
          $sql = "INSERT  INTO `proxy` ( `host`,`port`) VALUES ( '".$v[0]."', ".$v[1].") ";
          $mysql->runSql( $sql );
          $mysql->closeDb();}
          break;
        }
      }
    }
    return $raw;
  }
  
  function check($raw) {
    if(json_decode($raw) && !empty(json_decode($raw)->statusCode) && json_decode($raw)->statusCode == -1) return True;
    else return FALSE;
  }

  
  $action = empty($_GET['a'])?'':$_GET['a'];
  $re = !empty($_GET['s'])?strtoupper($_GET['s']):'';
  $te = !empty($_GET['t'])?($_GET['t']):'';
  if(!$re && !$te) $isIndex = True;
  else $isIndex = False;

?>
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.5, maximum-scale=1.5, minimum-scale=1">
    <?php if($isIOS) { ?>
    <link rel="apple-touch-icon" href="icon114.png"/>
    <?php } ?>
    <title><?php echo $re?$re.'车的实时信息-':''; echo $te?$te.'的实时信息-':'';  ?>广州实时公交</title>
    <style>
      body{
        width:100%;
        margin:20px auto;
        text-align:center;
        font-size:1em;
      }
      b,u {
        font-weight:900
      }
     <?php if($isJava) { ?>
      table {
        margin: 10px auto;
        text-align:left;
      }
      td,th {
        border:1px solid #666;
        margin:0;
      }
     <?php } else { ?>
      table {
        max-width:420px;
        margin: 10px auto;             
      }
      
     <?php } ?>
      .title {
        background:#ccc;
      }
      .search_result {
        font-size:24px;
      }
      <?php echo ($isIndex)?'form{padding-top:40px}':''; ?>
    </style>
  </head>
  <body>
    <?php if((!$re && !$te)) { ?>
    <h2>广州实时公交</h2>
    <?php } ?>
<?php if($re || (!$re && !$te)) { ?>    
    <form action="index.php" method="get" id="bus">
      <label>请输入查询<b>线路</b>：<input type="text" id="s" name="s" value="<?php echo $re; ?>" /></label>
      <input type="hidden" name="a" value="search" />
      <input type="submit" />
      <?php  if(!$re && !$te) { ?><p class="submit_tip"><small>如：801、B25、夜48、高峰5、高峰快线30...</small></p><?php } ?>
    </form>
 <?php } if($te || (!$re && !$te)) { ?>
    <form action="index.php" method="get" id="station">
      <label>请输入查询<b>站点</b>：<input type="text" id="s" name="t" value="<?php echo $te; ?>" /></label>
      <input type="hidden" name="a" value="find" />
      <input type="submit" />
      <?php  if(!$re && !$te) { ?><p class="submit_tip"><small>如：华师、体育中心站B1、华景新城总站...</small></p><?php } ?>
    </form>    
<?php } ?>    
  <?php
    if(empty($action)) {
    }
    elseif($action == 'search') {
      if(empty($_GET['s'])) err('-1');
      $search = $_GET['s'];
      //$raw_result = file_get_contents($search_url.iconv('utf-8','gb2312',strtoupper($search)),false,$context);
      $raw_result = getinfo('search',$search);
      $obj_result = json_decode($raw_result);
      if(empty($obj_result)) err('-102');
      if($obj_result->statusCode != -1) err('-2'); 
      //var_dump($obj_result);
      $route_list = explode(',', $obj_result->content);
      //var_dump($route_list);
      if(count($route_list) > 1) {
        echo '<table border="1" class="search_result">';
        echo '<tr><th>请选择线路：</th></tr>';
        foreach($route_list as $k => $v) {
          echo '<tr><td><a href="?a=detail&s='.urlencode($v).'">'.$v.'</a></td></tr>';
        }
        echo '</table>';
      }
      else {
            $route = $route_list[0];
      }
    }
    //处理查询站点
    elseif($action == 'find') {
      if(empty($_GET['t'])) err('-5');
      $search = $_GET['t'];
      //$raw_result = @file_get_contents($find_url.iconv('utf-8','gb2312',strtoupper($search)),false,$context);
      $raw_result = getinfo('find',$search);
      $obj_result = json_decode($raw_result);
      if(empty($obj_result)) err('-106');
      if($obj_result->statusCode != -1) err('-6');
      $station_list = explode(',', $obj_result->content);
      if(count($station_list) > 1) {
        echo '<table border="1" class="search_result">';
        echo '<tr><th>请选择站点：</th></tr>';
        foreach($station_list as $k => $v) {
          echo '<tr><td><a href="?a=station&t='.urlencode($v).'">'.$v.'</a></td></tr>';
        }
        echo '</table>';
      }
      else {
            $station = $station_list[0];
      }      
      
    }
    elseif($action == 'detail') {
      if(empty($_GET['s'])) err('-3');
      $route = $_GET['s'];
      //var_dump($route);
    }
    elseif($action == 'station') {
      if(empty($_GET['t'])) err('-7');
      $station = $_GET['t'];
    }
    
    //处理具体路线
    if(!empty($route)) {
      //$raw_result = @file_get_contents($detail_url.iconv('utf-8','gb2312',strtoupper($route)),false,$context);
      $raw_result = getinfo('detail',$route);
      $obj_result = json_decode($raw_result);
      if(empty($obj_result)) err('-104');
      if($obj_result->statusCode != -1) err('-4');
      $r = $obj_result;
      $lines = array();
      foreach($r->content as $k) {
	array_push($lines,$k);
      } 
      //var_dump($lines);
    ?>  
    <?php if(!$isJava && !$isJUC) { ?><h3><?php echo $route ?>&nbsp;&nbsp;<span id="time">25秒后自动</span><a href="javascript:void(0)" onclick="clearInterval(m);location.reload()">刷新</a></h3><?php } ?>
    <p><small>可将此页设为书签；点击车辆可两小时内跟踪</small></p>
    <table border ="1">
      <?php foreach($lines as $k => $v) { 
  $station_names = explode(',', $v->busLine->stationNames);
        //var_dump($station_names);
        $stations = array();
        foreach($station_names as $key => $val) {
          $stations[$key+1]['name'] = $val;
          $stations[$key+1]['bus'] = array();
        }
        //var_dump($stations);
        $terminals = $v->busTerminal;
        //var_dump($terminals);
        foreach($terminals as $bus) {
          $rk = $bus->stationSeq;
          array_push($stations[$rk]['bus'],$bus);          
        }
        //var_dump($stations);
      ?>
      <tr class="title"><th><?php if($v->busLine->lineDirection == 0) { ?>
    上行&nbsp;&dArr;
      <?php } elseif($v->busLine->lineDirection == 1) {?>
    下行&nbsp;&dArr;
    <?php } else echo $v->busLine->lineDirection ?>
        </th><th><?php echo $v->busLine->strPlatName ?>&nbsp;&rArr;&nbsp;<?php echo $v->busLine->endPlatName ?></th></tr>
      <?php foreach($stations as $st) { ?>
        <tr>
          <td><a href="index.php?a=find&t=<?php echo urlencode($st['name']); ?>"><?php echo $st['name']; ?></a></td>
          <td>      
      <?php foreach($st['bus'] as $bus) {
    echo '<span id="b'.$bus->equipKey.'" onclick="Follow.follow('.$bus->equipKey.')">';
  echo $bus->equipKey.'车:';
        $intime = strtotime($bus->inTime) + $m_err;
        //$api_time = time();   //重定义时间
        $diff_time = time() - $intime;  //使用当前时间 或 $api_time
        $out = '[<u time="'.($diff_time + 1).'">' .($diff_time<0?'-':''). date("i分s秒",abs($diff_time)).'</u>]';
        if($bus->adflag) $out .= '前已<b>进站</b>&nbsp;&dArr;';
        else $out .= '前已<b>出站</b>&nbsp;&dArr;';
        echo $out;
        echo '</span><br>';
        if($GLOBALS['debug']){
          if($bus->inTime != $bus->adTime) {
            $kv = new SaeKV();
            $ret = $kv->init();
            $ret = $kv->set('T'.time().rand(100,999), $bus->inTime.'||||'.$bus->adTime);
          }
          
          if($diff_time<0) {
            $mysql = new SaeMysql();
            $sql = "INSERT  INTO `times` ( `line`,`adflag`,`server_time`,`api_server_time`,`api_time`) ";
            $sql .= "VALUES ( '".strtoupper($route)."', ".intval($bus->adflag).", FROM_UNIXTIME(".time()."), FROM_UNIXTIME(".$GLOBALS['api_time']."), FROM_UNIXTIME(".$intime.")); ";
            $mysql->runSql( $sql );
            $mysql->closeDb();
          }
          
        }
      } ?>
            <?php if($isIE && !count($st['bus'])) echo "&nbsp;" ?>
          </td>
        </tr>
      <?php } ?>
      <?php } ?>
    </table>
  <script>
  var timer = document.getElementById('time');  var i = 25; var m = setInterval(function(){timer.innerHTML=i+'秒后自动';--i;if(i==0){clearInterval(m);location.reload();}},1000);
  //document.getElementById('s').value = '<?php echo strtoupper($route); ?>';
  var TimerList = document.getElementsByTagName('u');
  var n = setInterval(function(){
    for(var key=0;key<TimerList.length;++key){
      var unix = TimerList[key].getAttribute('time');
      var min = String(Math.abs(parseInt(unix/60))),
          sec = String(Math.abs(unix%60));
      TimerList[key].innerHTML = (parseInt(unix)<0?'-':'')+(min<10?("0"+min):min)+'分'+(sec<10?("0"+sec):sec)+'秒';
      TimerList[key].setAttribute('time',parseInt(unix)+1);
    }
  },1000);

  var Follow = {
    list:[<?php echo @$_COOKIE['bus']; ?>],
    follow:function(bus) {for(var i in this.list) {if(this.list[i]==bus) {this.unfollow(i,bus);return;}};this.list.push(bus);this.recookie();},
    unfollow:function(bus_i,bus) {this.list.splice(bus_i,1);this.color(bus);this.recookie();},
    recookie:function(){
      this.color();
      <?php if($isUC) { ?> //Fixed For UC Broswer;
      var newScript = document.createElement('script');
      newScript.src = "index.php?cookie="+this.list.join(",");
      document.body.appendChild(newScript);
      <?php  } else { ?>  
      var exp  = new Date();
      exp.setTime(exp.getTime() + 2*60*60*1000);
      document.cookie = 'bus=' + escape(this.list.join(',')) + ";expires=" + exp.toString() + ';';
      <?php } ?>
    },
    color:function(bus){
            if(bus) {
              var item = document.getElementById('b'+bus);
              item.style.color = '';
            }
          else {
            for(var i in this.list) {
              var item = document.getElementById('b'+this.list[i]);
              item.style.color = '#F00';              
            }
          }
    }
  };
  try{Follow.color();}catch(err){}
  </script>
    <?php
    }//结束处理具体路线
    
    //处理具体站点
    if(!empty($station)) {
      //$raw_result = @file_get_contents($station_url.iconv('utf-8','gb2312',strtoupper($station)),false,$context);
      $raw_result = getinfo('station',$station);
      $obj_result = json_decode($raw_result);
      if(empty($obj_result)) err('-108');
      if($obj_result->statusCode != -1) err('-8');
      $buses = $obj_result->content;
      if(!$isJava && !$isJUC) echo '<h3>'.$station.'&nbsp;&nbsp;<span id="time">25秒后自动</span><a href="javascript:void(0)" onclick="clearInterval(m);location.reload()">刷新</a></h3>';
      echo '<p><small>点击车辆可查看详情；查询站点的接口出错率高，建议使用<a href="index.php" >查询线路</a></small></p>';
      echo '<table border="1">';
      foreach($buses as $bus) {
        $bus_s = $bus->busStation;
        echo "<tr><td><b>";
        echo '<a href="index.php?a=detail&s='.urlencode($bus_s->lineName).'">';
        echo $bus_s->lineName;
        echo '</a>';
        echo "（";
        if($bus_s->direction == 0) echo "上行";
        elseif($bus_s->direction == 1) echo "下行";
        else echo $bus_s->direction;
        echo "）</b><br>";
        echo "".$bus_s->strPlatName."&nbsp;&rArr;&nbsp;".$bus_s->endPlatName;
        echo '</td><td>';
        echo '距离此站还有&nbsp;<b>'.$bus->leftStationNum.'</b>&nbsp;站';
        echo '</td></tr>';
      }
      echo '</table>';
    ?>
    <script>
     var timer = document.getElementById('time');  var i = 25; var m = setInterval(function(){timer.innerHTML=i+'秒后自动';--i;if(i==0){clearInterval(m);location.reload();}},1000);
     </script>
   <?php
      
    }
    ?>
    
    <p <?php if($isIndex) echo "style='padding-top:40px'" ?>><?php if($isIndex) echo "<small>* 这几天特别不稳定，请耐心等候刷新。</small><br>"; ?>
    <?php if(!$isIndex) { ?><a href="javascript:history.back();">后退</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="index.php" >返回首页</a>&nbsp;&nbsp;&nbsp;&nbsp;<?php } ?><?php if($isIndex) { ?><a href="http://gzmetro.sinaapp.com" target="_blank">广州地铁时间估算工具</a>&nbsp;&nbsp;&nbsp;&nbsp;<?php } ?><a href="review.html" >反馈</a></p>
  </body>
</html>
<?php
if($GLOBALS['debug']){
  $mysql = new SaeMysql();
  if($id = $mysql->getVar("select id from headers where user_agent like '".$mysql->escape( $_SERVER['HTTP_USER_AGENT'] )."';")) {   
      $sql = "update headers set count = count + 1 where id = ".$id;   
    }
    else {
      $sql = "INSERT  INTO `headers` ( `user_agent` ) VALUES ( '"  . $mysql->escape( $_SERVER['HTTP_USER_AGENT'] ) . "' ) ";      
    }
    $mysql->runSql( $sql );
    $mysql->closeDb();
}
?>
