<?php


class Biconomy
{
    public $url_base;
    public $api_key;
    public $secret_key;

    public function __construct($api_key = "", $secret_key = "")
    {
        if($api_key == "")      throw new Exception("param API_KEY is empty");
        if($secret_key == "")   throw new Exception("param SECRET_KEY is empty");

        $this->url_base = "https://www.biconomy.com";
        $this->api_key = $api_key;
        $this->secret_key = $secret_key;
    }

    public function tickers()
    {
        return $this->api("/api/v1/tickers");
    }

    public function ticker($pair = "")
    {
        if($pair == "") throw new Exception("param PAIR is empty");

        $pair = str_replace('/',"_", $pair);

        $tickers = json_decode( $this->api("/api/v1/tickers"), true );

        $arr = array();
        foreach($tickers['ticker'] AS $ticker){
            if($ticker['symbol'] == $pair) $arr = $ticker;
        }

        return $arr;
    }

    public function assets()
    {
        $return = array();

        $assets = $this->api("/api/v1/private/user", [
            "api_key"       => $this->api_key,
            "secret_key"    => $this->secret_key
        ]);

        $currencies = json_decode($assets, true);

        foreach($currencies['result'] AS $currency => $arr){
            if($currency=="user_id") continue;
            //echo $currency."\n";
            $available  = floatval( $arr['available'] );
            $freeze     = floatval( $arr['freeze'] );

            if($available>0 || $freeze>0){
                $return[$currency] = array(
                    "total" => $available + $freeze,
                    "used"  => $freeze,
                    "free"  => $available
                );
            }
        }

        return $return;
    }

    public function createOrder($symbol = "", $type = "", $size = 0, $side = "", $price = 0)
    {
        if($symbol == "")       throw new Exception("param SYMBOL is empty");
        if($type == "")         throw new Exception("param TYPE is empty");
        if($size == 0)          throw new Exception("param SIZE is empty");
        if($side == "")         throw new Exception("param SIDE is empty");
        if(!empty($type) && $type=="limit" && $price == 0)  throw new Exception("param PRICE is empty");

        $return = "";
        if($type=="limit") {
            $data = array(
                "amount"        => $size,
                "api_key"       => $this->api_key,
                "market"        => str_replace('/','_', $symbol),
                "price"         => $price,
                "side"          => ($side=="buy"?2:1),
                "secret_key"    => $this->secret_key
            );
            $return = $this->api("/api/v1/private/trade/limit", $data);
        } else if($type=="market") {
            $data = array(
                "amount"        => $size,
                "api_key"       => $this->api_key,
                "market"        => str_replace('/','_', $symbol),
                "side"          => ($side=="buy"?2:1),
                "secret_key"    => $this->secret_key
            );
            $return = $this->api("/api/v1/private/trade/market", $data);
        }

        return json_decode( $return, true );
    }

    public function cancelOrder($symbol = "", $order_id = 0)
    {
        if($symbol == "")   throw new Exception("param SYMBOL is empty");
        if($order_id == 0)  throw new Exception("param ORDER ID is empty");

        $data = array(
            "api_key"       => $this->api_key,
            "market"        => str_replace('/','_', $symbol),
            "order_id"      => $order_id,
            "secret_key"    => $this->secret_key
        );
        return json_decode( $this->api("/api/v1/private/trade/cancel", $data), true );
    }

    /**
     * I can`t use "Bulk Cancel Order" because the documentation not have more details,
     * so I delete one by one.
     */
    public function cancelAllOrders($symbol = "")
    {
        if($symbol == "")   throw new Exception("param SYMBOL is empty");
        
        $orders     = $this->getOpenOrders($symbol);
        $ids        = array();
        $c=1;
        foreach($orders['result']['records'] AS $order){
            if($order['source']!="api,127") continue;

            $ids[] = $order['id'];
        }

        $arr = array();
        foreach($ids AS $id){
            $arr[] = $this->cancelOrder($symbol, $id);
        }
        return $arr;
    }

    public function getOpenOrders($symbol = "")
    {
        if($symbol == "")       throw new Exception("param SYMBOL is empty");

        $data = array(
            "api_key"       => $this->api_key,
            "limit"         => 100,
            "market"        => str_replace('/','_', $symbol),
            "offset"        => 0,
            "secret_key"    => $this->secret_key
        );

        return json_decode( $this->api("/api/v1/private/order/pending", $data), true );
    }

    public function getClosedOrders($symbol = "", $side = "", $only_api = true)
    {
        if($symbol == "")       throw new Exception("param SYMBOL is empty");
        if($side == "")         throw new Exception("param SIDE is empty");

        $data = array(
            "api_key"       => $this->api_key,
            "limit"         => 100,
            "market"        => str_replace('/','_', $symbol),
            "offset"        => 0,
            "side"          => ($side=="buy"?2:1),
            "secret_key"    => $this->secret_key
        );

        $result = json_decode( $this->api("/api/v1/private/order/finished", $data), true );
        if($only_api){
            foreach($result['result']['records'] AS $key => $arr){
                if($arr['source']!="api,127") unset($result['result']['records'][$key]);
            }
        }

        return $result;
    }

    public function getOpenOrder($symbol = "", $order_id = 0)
    {
        if($symbol == "")       throw new Exception("param SYMBOL is empty");
        if($order_id == 0)      throw new Exception("param ORDER ID is empty");

        $data = array(
            "api_key"       => $this->api_key,
            "market"        => str_replace('/','_', $symbol),
            "order_id"      => $order_id,
            "secret_key"    => $this->secret_key
        );

        return json_decode( $this->api("/api/v1/private/order/pending/detail", $data), true );
    }

    public function getFinishedOrder($order_id = 0)
    {
        if($order_id == 0)      throw new Exception("param ORDER ID is empty");

        $data = array(
            "api_key"       => $this->api_key,
            "order_id"      => $order_id,
            "secret_key"    => $this->secret_key
        );

        return json_decode( $this->api("/api/v1/private/order/pending/detail", $data), true );
    }


    /* ----- */

    public function api($endpoint, $post = array())
    {
        $headers    = array(
            'Content-Type: application/x-www-form-urlencoded',
            "X-SITE-ID: 127"
        );

        $url    = $this->url_base.$endpoint;

        $ch     = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if(count($post)>0){
            $encoded    = http_build_query($post);
            $md5        = strtoupper(md5($encoded));

            $p = $post;
            unset($p['secret_key']);

            $send =  http_build_query($p)."&sign=".$md5;

            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $send );
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $return   = curl_exec($ch);

        curl_close($ch);

        return $return;
    }
}



?>