<?php require_once("UrlSigning.php"); ?>
<?php
/*
*
* Document format handling. Determines what format needs to be delivered and returns this to the browser - if the url has been signed
* properly
*
*/

// sets the cache header
function setNoCacheHeader($timestamp=null){
    if(!$timestamp){
        $seconds_to_cache = 3600; // to be passed as an argument to the signing method (suggested to be 10 minutes)
        $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
    }else{
        $seconds_to_cache = strtotime($timestamp) - strtotime(date('Y-m-d H:i:s'));
        $ts = gmdate('Y-m-d H:i:s', strtotime($timestamp) ) . " GMT";
    }

    header("Expires: $ts");
    header("Pragma: cache");
    header("Cache-Control: max-age=$seconds_to_cache");
}

// checks for malformed requests
function validParams($path,$doc,$page){
    return !(	strlen($doc) > 255 ||
                strlen($page) > 255 ||
                ($page !=null && is_numeric($page) == false) ||
                strpos($path . $doc . $page, "..") > 0 ||
                strpos($path . $doc . $page, "//") > 0 ||
                preg_match("=^[^/?*;:{}\\\\]+[^/?*;:{}\\\\]+$=",$page . $doc)==0
            );
}

// arguments
$doc 		    = $_GET["doc"];
$pdfdoc 	    = $doc . ".pdf";
$format         = $_GET["format"];
$page           = (isset($_GET["page"]))?$_GET["page"]:"";
$raw_signature  = $_GET["signature"];
$expiry         = $_GET["expires"];
$expiry_date    = UrlSigning::expiryToDate($expiry);
$key_md5        = substr($raw_signature,0,32);
$signature      = substr($raw_signature,32); // the last characters is the actual signature
$request_uri    = $_SERVER['REQUEST_URI'];
$signed_url     = substr($request_uri,strpos($request_uri,"get_signed_content.php")+22);

parse_str($signed_url, $url_params);

$signed_parsed  = "get_signed_content.php" . UrlSigning::sortAndClean($url_params,false,true);
$parsed_url     = "get_signed_content.php" . UrlSigning::sortAndClean($url_params,true,true);
$url_verified   = UrlSigning::verifySignedUrl($parsed_url,$key_md5,$raw_signature) && (date('Y-m-d H:i:s') < $expiry_date);

if(!$url_verified || !validParams("docs/".$pdfdoc,$doc,$page)){
    echo "URL not recognized or content expired";

    die();
}


// JSON/JSONP format
if($format == "json" || $format == "jsonp"){

    // file path
    $jsondoc    = $pdfdoc . "_" . $page . ".js.php";
    $jsonFilePath 	= "docs/" . $jsondoc;

    if(file_exists($jsonFilePath)){
        header('Content-Type: text/javascript');

        if($format == "json"){
            echo file_get_contents($jsonFilePath,false,null,41);
        }

        if($format == "jsonp"){
            $callback = $_GET["callback"];
            echo $callback. '('. file_get_contents($jsonFilePath,false,null,41) . ')';
        }
    }
}

if($format == "swf"){
    if(strpos($page,"0") == 0){
        $page = substr($page,1);
    }

    $swfdoc 	    = $pdfdoc . "_" . $page . ".swf.php";
    $swfFilePath    = "docs/" . $swfdoc;

    header('Content-type: application/x-shockwave-flash');
    header('Accept-Ranges: bytes');
    setNoCacheHeader($expiry_date);
    header('Content-Length: ' . filesize($swfFilePath)-41);

    echo file_get_contents($swfFilePath,false,null,41);
}

if($format == "pdf"){
    $pdfSplitPath    = "docs/" . $doc . "_" . $page . ".pdf.php";

    header('Content-type: application/pdf');
    setNoCacheHeader($expiry_date);

	echo file_get_contents($pdfSplitPath,false,null,41);
}

if($format == "jpg"){
    header('Content-Type: image/jpeg');
    header('Accept-Ranges: bytes');
    setNoCacheHeader($expiry_date);

    if(isset($_GET["resolution"])){
        $jpegFilePath   = "docs/" . $pdfdoc . "_" . $page . "_thumb.jpg";

        header('Content-Length: ' . filesize($jpegFilePath));
        echo file_get_contents($jpegFilePath);

    }else{
        $jpegFilePath   = "docs/" . $pdfdoc . "_" . $page . ".jpg.php";

        header('Content-Length: ' . filesize($jpegFilePath)-41);
        echo file_get_contents($jpegFilePath,false,null,41);
    }
}

if($format == "highrespng"){
    $pngPath = "docs/" . $pdfdoc . "_" . $page . "_highres.png.php";
    header('Content-Type: image/png');
    header('Accept-Ranges: bytes');
    setNoCacheHeader($expiry_date);

    header('Content-Length: ' . filesize($pngPath)-41);
    echo file_get_contents($pngPath,false,null,41);
}

if($format == "jpgpageslice"){
    $urlType = 'http';
    if ($_SERVER["HTTPS"] == "on") {$urlType .= "s";}

    $urlPath    = $urlType."://".$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"]."?doc=".$doc."&signature=".$raw_signature."&format=highrespng&expires=".$expiry."&page=".$page;
    $sector     = $_GET["sector"];

    //get image size
    $size = getimagesize($urlPath);
    $width = $size[0];
    $height = $size[1];

    //get source image
    $func = "imagecreatefrompng";
    $source = $func($urlPath);

    //setting default values
    $k_w = 1;
    $k_h = 1;
    $src_x =0;
    $src_y =0;
    $margin_x =0;
    $margin_y =0;

    $attempted_width =  $width * .25; //+(1-$margin_x);
    $attempted_height = $height * .25; //+(1-$margin_y);

    $width_decimals = $attempted_width - floor($attempted_width);
    if($width_decimals > 0 && $width_decimals < 0.5){
        $attempted_width = $attempted_width + 0.49;
    }

    $height_decimals = $attempted_height - floor($attempted_height);
    if($height_decimals > 0 && $height_decimals < 0.5){
        $attempted_height = $attempted_height + 0.49;
    }

    $push   = intval(round($attempted_width)) - floor($attempted_width);
    $pushv  = intval(round($attempted_height)) - floor($attempted_height);

    switch($sector){
        // top 50%, left 50%
        case "l1t1":
            $src_x = 0;
            $src_y = 0;
        break;
        case "l2t1":
            $src_x = $width * .25;
            $src_y = 0;

            if($push > 0){
                $src_x = $src_x + $push;
            }
        break;
        case "l1t2":
            $src_x = 0;
            $src_y = $height * .25;

            if($pushv > 0){
                $src_y = $src_y + $pushv;
            }
        break;
        case "l2t2":
            $src_x = $width * .25;
            $src_y = $height * .25;

            if($push > 0){
                $src_x = $src_x + $push;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv;
            }
        break;

        // top 50%, right 50%
        case "r1t1":
            $src_x = $width * .5;
            $src_y = 0;

            if($push > 0){
                $src_x = $src_x + $push*2;
            }
        break;
        case "r2t1":
            $src_x = $width * .75;
            $src_y = 0;

            if($push > 0){
                $src_x = $src_x + $push*3;
            }
        break;
        case "r1t2":
            $src_x = $width * .5;
            $src_y = $height * .25;

            if($push > 0){
                $src_x = $src_x + $push*2;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv;
            }
        break;
        case "r2t2":
            $src_x = $width * .75;
            $src_y = $height * .25;

            if($push > 0){
                $src_x = $src_x + $push*3;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv;
            }
        break;

        //bottom 50%, left 50%
        case "l1b1":
            $src_x = 0;
            $src_y = $height * .5;

            if($pushv > 0){
                $src_y = $src_y + $pushv*2;
            }
        break;
        case "l2b1":
            $src_x = $width * .25;
            $src_y = $height * .5;

            if($push > 0){
                $src_x = $src_x + $push;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv*2;
            }
        break;
        case "l1b2":
            $src_x = 0;
            $src_y = $height * .75;

            if($pushv > 0){
                $src_y = $src_y + $pushv*3;
            }
        break;
        case "l2b2":
            $src_x = $width * .25;
            $src_y = $height * .75;

            if($push > 0){
                $src_x = $src_x + $push;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv*3;
            }
        break;

        // bottom 50%, right 50%
        case "r1b1":
            $src_x = $width * .5;
            $src_y = $height * .5;

            if($push > 0){
                $src_x = $src_x + $push*2;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv*2;
            }
        break;
        case "r2b1":
            $src_x = $width * .75;
            $src_y = $height * .5;

            if($push > 0){
                $src_x = $src_x + $push*3;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv*2;
            }
        break;
        case "r1b2":
            $src_x = $width * .5;
            $src_y = $height * .75;

            if($push > 0){
                $src_x = $src_x + $push*2;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv*3;
            }
        break;
        case "r2b2":
            $src_x = $width * .75;
            $src_y = $height * .75;

            if($push > 0){
                $src_x = $src_x + $push*3;
            }

            if($pushv > 0){
                $src_y = $src_y + $pushv*3;
            }
        break;
    }

    // adjusting for rounding (subpixel adjustment)
    //$margin_x = $src_x - floor($src_x);
    //$margin_y = $src_y - floor($src_y);

    $new_width = intval(round($attempted_width));
    $new_height = intval(round($attempted_height));

    header('Content-Type: image/png');
    $output = imagecreatetruecolor( $new_width, $new_height	);
    imagecopyresampled($output, $source, 0, 0, $src_x, $src_y,$new_width,$new_height,$new_width,$new_height);

    imagepng($output,NULL,7);
}

if($format == "png"){
    $pngFilePath   = "docs/" . $pdfdoc . "_" . $page . ".jpg.php";
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($pngFilePath)-41);
    setNoCacheHeader($expiry_date);

    echo file_get_contents($pngFilePath,false,null,41);
}
?>