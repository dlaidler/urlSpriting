<?php
$start = microtime();
$url = "http://test.local.vm/sprite/test.php";
$baseurl = "http://test.local.vm/sprite";

if (isset($_GET['url'])) {
  $url = $_GET['url'];
  $split = explode('/', $url);
  $lastelement = $split[count($split) - 1];
  //if (stristr($lastelement, '.php') || stristr($laselement, '.html')) {
  array_pop($split);
  $baseurl = implode('/', $split);
  //}
  $rooturl = 'http://' . $split[2];
}

$padding = 0;
if (isset($_GET['padding'])) {
  if (is_int($_GET['padding']))
    $padding = $_GET['padding'];
}

$br = "<br />";
$pointers = array();
$widths = array();
$urls = array();
$max_height = 0;
$min_height = PHP_INT_MAX;
$check = 0;
$response = scanFile($url);
$rect_area = 0;

$sprite['width'] = 0;
$sprite['height'] = 0;

array_multisort($widths, SORT_DESC, SORT_NUMERIC, $pointers);
$sprite['width'] = $max_width = $widths[0];
$min_width = $widths[count($widths) - 1];

//header("Content-Type: image/jpg");
$sprite_im = @imagecreatetruecolor($sprite['width'], 10000)
        or die("Cannot Initialize new GD image stream");

$background_color = imagecolorallocate($sprite_im, 254, 254, 254);

imagefill($sprite_im, 0, 0, $background_color);
imagecolortransparent($sprite_im, $background_color);

$images_size = 0;
$images_area = 0;
$availspace = array();

foreach ($pointers as &$pointer) {
  if ($pointer['mime'] == 'image/jpeg')
    $im = imagecreatefromjpeg($pointer['url']);
  elseif ($pointer['mime'] == 'image/png')
    $im = imagecreatefrompng($pointer['url']);
  elseif ($pointer['mime'] == 'image/gif')
    $im = imagecreatefromgif($pointer['url']);

  $images_size += $pointer['filesize'];
  $images_area += $pointer['width'] * $pointer['height'];

  if (count($availspace) == 0) {
    //echo $pointer['url'].$br;
    $x = 0;
    $y = $sprite['height'];
    $sprite['height'] += $pointer['height'] + $padding;
    $availwidth = $sprite['width'] - ($pointer['width'] + $padding);
    $availheight = $pointer['height'];
    imagefillfromfile($sprite_im, $im, $x, $y);
    if ($availwidth >= $min_width + $padding) {
      array_push($availspace, array(
          'x' => ($pointer['width'] + $padding),
          'y' => $y,
          'width' => $availwidth,
          'height' => $availheight)
      );
    }
  } else {
    $not_placed = true;
    // iterate through availspace until width of next item can fit, check availspace height, if less place to right
    foreach ($availspace as $key=>$space) {
      if ($space['width'] >= $pointer['width'] && $space['height'] >= $pointer['height']) {
        //echo $pointer['url'].$br;
        $x = $space['x'];
        $y = $space['y'];
        $availwidth_right = $space['width'] - ($pointer['width'] + $padding);
        $availheight_bottom = $space['height'] - ($pointer['height'] + $padding); 
        $availheight = $space['height'];
        imagefillfromfile($sprite_im, $im, $x, $y);
        $not_placed = false;
        if ($availwidth >= $min_width + $padding) {
          array_push($availspace, array(
              'x' => ($x + $pointer['width'] + $padding),
              'y' => $y,
              'width' => $availwidth_right,
              'height' => $availheight)
          );
        }
        if ($availheight >= $min_height + $padding) {
          array_push($availspace, array(
              'x' => ($x + $padding),
              'y' => ($y + $pointer['height'] + $padding),
              'width' => $pointer['width'],
              'height' => $availheight_bottom)
          );
        }
        unset($availspace[$key]);
        break;
      }
    }
    if ($not_placed) {
      $x = 0;
      $y = $sprite['height'];
      $sprite['height'] += $pointer['height'] + $padding;
      $availwidth = $sprite['width'] - ($pointer['width'] + $padding);
      $availheight = $pointer['height'];
      imagefillfromfile($sprite_im, $im, $x, $y);
      if ($availwidth >= $min_width + $padding) {
        array_push($availspace, array(
            'x' => ($pointer['width'] + $padding),
            'y' => $y,
            'width' => $availwidth,
            'height' => $availheight)
        );
      }
    }
  }




  // sort availspace by width avail
  $pointer['x'] = $x;
  $pointer['y'] = $y;
}

//var_dump($availspace);

//$newheight += $s1['height'];


$sprite_im_resize = @imagecreatetruecolor($sprite['width'], $sprite['height'])
        or die("Cannot Initialize new GD image stream");
imagefill($sprite_im_resize, 0, 0, $background_color);

imagecopy($sprite_im_resize, $sprite_im, 0, 0, 0, 0, $sprite['width'], $sprite['height']);

imagecolortransparent($sprite_im_resize, $background_color);

$fileName = "sprite.png";

imagepng($sprite_im_resize,$fileName,9);

imagedestroy($sprite_im_resize);
imagedestroy($sprite_im);


function curl_get_contents($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_REFERER, 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // Timeout in seconds
  //curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  $output = curl_exec($ch);
  curl_close($ch);
  return $output;
}

function strrevpos($contents, $offset) {
  $start = $offset;
  $not_found = false;
  while ($contents[$start] != '"' && $contents[$start] != "'" && $contents[$start] != "(" && substr($contents, $start, 4) != "src=") {
    $start--;
    if ($start <= 0 || $contents[$start] == " ") {
      $not_found = true;
      break;
    }
  }
  $startchars = 1;
  if (substr($contents, $start, 4) == "src=")
    $startchars = 4;
  if ($not_found)
    return false;
  else
    return substr($contents, $start + $startchars, $offset - ($start + $startchars - 1));
}

function get_filesize($contents) {
  $fileName = "testFile.txt";
  $fp = fopen($fileName, 'w') or die("can't open file");
  fwrite($fp, $contents);
  fclose($fp);
  $filesize = filesize($fileName);
  unlink($fileName);
  return $filesize;
}

function scanFile($url, $level=0, $pointer=0) {

  global $baseurl;
  global $pointers;
  global $widths;
  global $max_height;
  global $min_height;
  global $check;
  global $urls;
  global $br;

  $pad = "";
  $response = "";

  while ($level > 0) {
    $pad .= "&nbsp;&nbsp;";
    $level--;
  }

  $contents = curl_get_contents($url);
  $response .= $pad . "<strong>$url: </strong>" . $br;

  while ($pointer !== null && $pointer < strlen($contents)) {
    if (!is_int($pointer))
      return;
    $check++;
    $interations = empty($_GET['test']) ? 0 : $_GET['test'];
    if ($interations && $check > $interations) {
      $response .= "Testing Testing 1 2 3!";
      break;
    }
    $img = array();
    $pointer++;
    $filetype = 'css';
    $css = strpos($contents, "." . $filetype, $pointer) + strlen($filetype);
    $cssFile = strrevpos($contents, $css);
    if (!empty($cssFile) && !stristr($cssFile, 'http')) {
      $slash = $cssFile[0] == "/" ? "" : "/";
      $cssFile = $baseurl . $slash . $cssFile;
    }

    if ($cssFile && strlen(stristr(curl_get_contents($cssFile), "<title>404 Not Found</title>")) == 0) {
      $response .= scanFile($cssFile, 1);
      $pointer = $css;
      continue;
    }

    $filetypes = array('jpg', 'jpeg', 'gif', 'png');

    foreach ($filetypes as $filetype) {
      $nxtptr = strpos($contents, "." . $filetype, $pointer);
      $nxtptr > 0 ? $img[$filetype] = $nxtptr : null;
    }

    $pointer = count($img) > 0 ? min(array_values($img)) : null;
    $typelength = strlen(array_search($pointer, $img));

    $imgFile = strrevpos($contents, $pointer + $typelength);
    if ($imgFile != null) {
      if (!stristr($imgFile, 'http')) {
        $slash = $imgFile[0] == "/" ? "" : "/";
        $imgFile = $baseurl . $slash . $imgFile;
      }
      if (array_search($imgFile, $urls) === false) {
        $imageAtt = getimagesize($imgFile);
        $file = file_get_contents($imgFile);
        $filesize = get_filesize($file);
        $attArray = array('url' => $imgFile, 'filesize' => $filesize, 'width' => $imageAtt[0], 'height' => $imageAtt[1], 'bits' => $imageAtt['bits'], 'mime' => $imageAtt['mime']);
        $response .= $pad . $imgFile . " : " . $pointer . $br;
        array_push($urls, $imgFile);
        if (!empty($imageAtt['channels']))
          $attArray = array_merge($attArray, array('channels' => $imageAtt['channels']));
        array_push($pointers, $attArray);
        array_push($widths, $attArray['width']);
        if ($attArray['height'] > $max_height)
          $max_height = $attArray['height'];
        if ($attArray['height'] < $min_height)
          $min_height = $attArray['height'];
      }
    }
  }
  $response .= $pad . "---------" . $br;
  return $response;
}

function imagefillfromfile($canvas, $image, $x, $y) {
  $imageWidth = imagesx($image);
  $imageHeight = imagesy($image);
  imagecopy($canvas, $image, $x, $y, 0, 0, $imageWidth, $imageHeight);
  return($canvas);
}

// COMMAND LINE IMPLEMENTATION
//exec("clear");
//if (isset($argv) && ($argc === 1 || $argc > 3)) {
//  echo "Usage...
//    getSprite [http://www.example.com]\n\n";
//  die;
//} else {
//  $br = "\n";
//  if(isset($argv))
//    $url = $argv[1];
//  else {
//    $url = "http://test.local.vm/sprite/test.php"; 
//    $br = "<br />";
//  }
//}
//$baseurl = '';
//$pointers = array();
//$baseurl = explode('/',$url);
//array_pop($baseurl);
//$baseurl = implode('/',$baseurl);
?>
<head>
<!--  <script src='http://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js' type='text/javascript' ></script>-->
  <script src='http://test.local.vm/lib/import/jquery/jquery-1.4.2.min.js' type='text/javascript' ></script>
  <link href='http://fonts.googleapis.com/css?family=Asap:400,700' rel='stylesheet' type='text/css'>
  <style>
    .rect { display:block; float: left; }
    .toggle {font-size: 10px; color: blue; cursor: pointer}
    body {font-family: 'Asap', Arial, "Georgia"; letter-spacing: 1px;}
    #image_array {display: none}

  </style>
<body>

</head>
<h4>Images Found: <span onclick='toggle("#images_found")' class='toggle'>View</span></h4><p id='images_found'>
<?php echo $response; ?>
</p>

<h4>Image Array: <span onclick='toggle("#image_array")' class='toggle'>View</span></h4><pre id='image_array'>
<?php print_r($pointers); ?>
</pre>
<?php echo empty($widths) ? '<h4>No Images Found!</h4>' : ''; ?>
<h4>Sprite:</h4>

<img src="<?php echo $fileName; ?>"/>

<h4>Generated CSS:</h4>

<?php
$end = microtime();
$imageAtt = getimagesize($fileName);
$file = file_get_contents($fileName);
$filesize = get_filesize($file);
echo "<p id='details'>
  Time Taken: " . round(($end - $start), 5) . " secs $br $br
  Area of Original Images: $images_area sq px $br
  Size of Original Images: $images_size bytes $br $br
  Area of New Sprite: ".$imageAtt[0]*$imageAtt[1]." sq px $br
  Size of New Sprite: $filesize bytes $br $br
  
  Difference in Area: ".round((($imageAtt[0]*$imageAtt[1]) / $images_area) * 100,2)."% $br
  Difference in Size: ".round($filesize/$images_size*100,2)."%
        
        </p>";
?>

<script>
  
  function toggle(ele) {
    if($(ele).css('display') == 'block') 
      $(ele).css('display','none');
    else
      $(ele).css('display','block')
  }
  
  $(document).ready(function(){ $('#images_found').css('display','none'); })
  
</script>

</body>