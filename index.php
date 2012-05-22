<?php
  ob_start("ob_gzhandler");
  require 'config.php'; //load API url 因为此接口原非公开，所以暂不公布;
  date_default_timezone_set('Asia/Shanghai');
      function err($code) {
        echo '<p style="padding-top:20px;">0_o 接口不稳定，<span id="timer">15秒后自动</span><a href="javascript:void(0)" onclick="location.reload()">刷新</a></p>';
        echo "<script>var timer = document.getElementById('timer');  var i = 15; var m = setInterval(function(){timer.innerHTML=i+'秒后自动';--i;if(i==0){clearInterval(m);location.reload();}},1000);</script>";        
        die('<style>form{padding-top:100px}</style></body></html>');
      }
  $opts = array( 
    'http'=>array( 
    'method'=>'GET', 
    'timeout'=>30, 
  )); 
  $context = stream_context_create($opts); 

  $action = empty($_GET['a'])?'':$_GET['a'];
  $re = !empty($_GET['s'])?strtoupper($_GET['s']):'';

?>
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo $re?$re.'车的实时信息-':'';  ?>广州实时公交</title>
    <style>
      body{
        width:100%;
        margin:20px auto;
        text-align:center;
        font-size:1em;
      }
      table {
        max-width:420px;
        margin: 10px auto;
      }
      .title {
        background:#ccc;
      }
      .search_result {
        font-size:24px;
      }
      <?php echo !$re?'form{padding-top:100px}':''; ?>
    </style>
  </head>
  <body>
    <form action="index.php" method="get">
      <label>请输入查询线路：<input type="text" id="s" name="s" value="<?php echo $re; ?>" /></label>
      <input type="hidden" name="a" value="search" />
      <input type="submit" />
    </form>
    <?php
      if(empty($action)) {
      }
      elseif($action == 'search') {
        if(empty($_GET['s'])) err('-1');
        $search = $_GET['s'];
        $raw_result = @file_get_contents($search_url.iconv('utf-8','gb2312',strtoupper($search)),false,$context);
        $obj_result = json_decode($raw_result);
        if(empty($obj_result) || $obj_result->statusCode != -1) err('-2');
        //var_dump($obj_result);
        $route_list = explode(',', $obj_result->content);
        //var_dump($route_list);
        if(count($route_list) > 1) {
    echo '<table border="1" class="search_result">';
    echo '<tr><th>请选择线路：</th></tr>';
    foreach($route_list as $k => $v) {
      echo '<tr><td><a href="?a=detail&s='.$v.'" >'.$v.'</a></td></tr>';
    }
    echo '</table>';
  }
  else {
    $route = $route_list[0];
  }
      }

    elseif($action == 'detail') {
      if(empty($_GET['s'])) err('-3');
      $route = $_GET['s'];
      //var_dump($route);
    }
    
    if(!empty($route)) {
      $raw_result = @file_get_contents($detail_url.iconv('utf-8','gb2312',strtoupper($route)),false,$context);
      //var_dump($raw_result);
      $obj_result = json_decode($raw_result);
    //var_dump($obj_result);
      if(empty($obj_result) || $obj_result->statusCode != -1) err('-4');
      $r = $obj_result;
      $lines = array();
      foreach($r->content as $k) {
    array_push($lines,$k);
      } 
      //var_dump($lines);
    ?>  
    <h3><?php echo $route ?>&nbsp;&nbsp;<span id="timer">25秒后自动</span><a href="javascript:void(0)" onclick="location.reload()">刷新</a></h3>
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
      </th><td>始发站：<?php echo $v->busLine->strPlatName ?>，终点站：<?php echo $v->busLine->endPlatName ?></td></tr>
      <?php foreach($stations as $st) { ?>
        <tr>
          <td><?php echo $st['name'] ?></td>
          <td>
      <?php foreach($st['bus'] as $bus) {
    echo '<span name="b'.$bus->equipKey.'" onclick="Follow.follow('.$bus->equipKey.')">';
        echo $bus->equipKey.'号车在';
        $intervalo = date_diff(date_create($bus->inTime), date_create());
        $out = $intervalo->format("<b>&nbsp;%i分钟%s秒&nbsp;</b>前到达此站&nbsp;&dArr;");
        echo $out;
        echo '</span><br>';        
      } ?>      
          </td>
        </tr>
      <?php } ?>
      <?php } ?>
    </table>
  <script>
  var timer = document.getElementById('timer');  var i = 25; var m = setInterval(function(){timer.innerHTML=i+'秒后自动';--i;if(i==0){clearInterval(m);location.reload();}},1000);
  document.getElementById('s').value = '<?php echo strtoupper($route); ?>';
  var Follow = {
    list:[<?php echo @$_COOKIE['bus']; ?>],
    follow:function(bus) {for(var i in this.list) {if(this.list[i]==bus) {this.unfollow(i,bus);return;}};this.list.push(bus);this.recookie();},
    unfollow:function(bus_i,bus) {this.list.splice(bus_i,1);this.color(bus);this.recookie();},
    recookie:function(){
      this.color();
      var exp  = new Date();
      exp.setTime(exp.getTime() + 2*60*60*1000);
      document.cookie = 'bus=' + escape(this.list.join(',')) + ";expires=" + exp.toString() + ';';    
    },
    color:function(bus){
            if(bus) {
              var items = document.getElementsByName('b'+bus);
              for(var a = 0; a < items.length; ++a) {
                      items[a].style.color = '';
              }    
            }
          else {
            for(var i in this.list) {
              var items = document.getElementsByName('b'+this.list[i]);
              for(var a = 0; a < items.length; ++a) {
                items[a].style.color = '#F00';
              }
            }
          }
    }
  };
  try{Follow.color();}catch(err){}
  </script>
    <?php
    }
    ?>  
  </body>
</html>