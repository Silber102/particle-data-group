<?php
class UrlSigning {
    public static $salt = "J-@LpSs7b/o!xkux#:A&BNO#Dq-rxqiQf!ilmTacwxkbTgo4l6w0g+wx1(xF6KhG";

    // Encode a string to URL-safe base64
    public static function encodeBase64UrlSafe($value)
    {
      return str_replace(array('+', '/'), array('-', '_'),
        base64_encode($value));
    }

    // Decode a string from URL-safe base64
    public static function decodeBase64UrlSafe($value)
    {
      return base64_decode(str_replace(array('-', '_'), array('+', '/'),
        $value));
    }

    // Sign a URL with a given crypto key
    // Note that this URL must be properly URL-encoded
    public static function signUrl($urlToSign, $privateKey, $expiry, $verify=false)
    {
      $expiry_str = "";

      if(!$verify){
          $privateKey = md5($privateKey);
          $expiry_str = "&expires=".strtotime($expiry . " UTC");
      }else{
          $expiry_str = "";
      }

      // parse the url
      $url = parse_url($urlToSign);
      $urlPartToSign = $url['path'] . "?" . $url['query'] .$expiry_str;
      parse_str($urlPartToSign, $url_params);
      $urlPartToSign = $url['path'] . "?" . UrlSigning::sortAndClean($url_params);

      // Decode the private key into its binary format
      $decodedKey = UrlSigning::decodeBase64UrlSafe($privateKey . UrlSigning::$salt);

      // Create a signature using the private key and the URL-encoded
      // string using HMAC SHA1. This signature will be binary.
      $signature = hash_hmac("sha1",$urlPartToSign, $decodedKey,  true);

      $encodedSignature = UrlSigning::encodeBase64UrlSafe($signature);
      $complete_url = $urlToSign."&signature=".$privateKey.$encodedSignature.$expiry_str;
      parse_str(substr($complete_url,strpos($complete_url,"get_signed_content.php")+22), $url_params);

      return $url['path'] . UrlSigning::sortAndClean($url_params);
    }

    // verifies a signed url against a supplied privatekey in MD5 format
    public static function verifySignedUrl($urlToVerify,$privateKey_md5,$raw_signature){
        $generatedUrl = UrlSigning::signUrl($urlToVerify,$privateKey_md5,"",true);
        parse_str($generatedUrl, $url_params);

        return $raw_signature == $url_params['signature'];
    }

    // sorts and cleans the array with parameters and returns the full url back again
    public static function sortAndClean($array,$cleanSignature=false,$resetFormatArguments=false){
        foreach ($array as $key => $val) {
            $doc = "";

            if( strpos($key,"signed_content_php")>0 || $key == "callback" || $key == "resolution" ){
               unset($array[$key]);
            }

            if( $resetFormatArguments ){
                if( $key == "page" ){
                   $array[$key] = "[*,2]";
               }

               if( $key == "format" ){
                    $array[$key] = "{format}";
               }
            }

            if( $cleanSignature ){
               if( $key == "signature" ){
                   unset($array[$key]);
               }

               if( $key == "_" ){ // jQuery forced refresh parameter
                   unset($array[$key]);
               }

               if( $key == "sector" ){
                   unset($array[$key]);
               }
            }
        }

        ksort($array);

        return urldecode(http_build_query($array));
    }

    // generate a expiry date by adding time to a UTC date
    public static function getExpiryDate($timeToAdd){
        date_default_timezone_set('UTC');
        return date('Y-m-d H:i:s', strtotime($timeToAdd));
    }

    // convert a date in int format back to date
    public static function expiryToDate($what){
        date_default_timezone_set('UTC');
        return date('Y-m-d H:i:s',$what);
    }

    // get a signed url from a license key
    public static function getSignedDOCURI($doc,$key,$expireat,$totalpages){
        return "'{" . UrlSigning::signUrl("get_signed_content.php?doc=" . $doc . "&format={format}&page=[*,2]",$key,UrlSigning::getExpiryDate($expireat))  ."," . $totalpages . "}'";
    }
}
?>