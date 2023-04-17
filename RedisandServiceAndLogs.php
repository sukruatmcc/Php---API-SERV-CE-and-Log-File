<?php
defined('BASEPATH') or exit('No direct script access allowed');
error_reporting(1);
// require 'Predis/Autoloader.php';
require __DIR__.'/../../vendor/autoload.php';
Predis\Autoloader::register();

class RedisandServiceAndLogs extends CI_Controller
{

    public function file_log($text,$name){
        $mode = "a+";
        $text = "\n".date("Y-m-d H:i:s")." ".$text;
        $file_name = $name.".txt";
        $file = fopen(__DIR__ ."/logs/$file_name", $mode);
        fwrite($file, $text);
        fclose($file);
    }

    public function query_test()
    {
        $data = array();
        $data["scan_date"] = "2022-10-28";
        // var_dump($data);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://167.235.247.56/admin_se3434/test_method',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_SSL_VERIFYHOST=>false,
            CURLOPT_POSTFIELDS =>json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
                
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
       // echo  $response;
        return $response;
    }
    
    public function test_method()
    {
        //    echo "Post edilen veri<br>";
        $string_data = trim(file_get_contents("php://input"));
        $json_data = json_decode($string_data);
        try {
            if($json_data){
                //post ile gelen verinin alınması
                $date = date("Y-m-d",strtotime($json_data->scan_date));
                if(!($json_data->scan_date == $date)){
                    echo json_encode(array("status"=>"error","message"=>"date bulunamadı"));
                    exit;
                }
                //redis cache kontrolü
                $redis = new Predis\Client('tcp://167.235.247.56:6379');
                $cacheEntry =  $redis->get("scan_date_".$json_data->scan_date);
                if($cacheEntry){
                    //echo "From Redis Cache"
                    echo json_encode(array("status"=>"success","database"=>"redis","message"=>$cacheEntry));
                    exit;
                }else{
                    $this->db->where("DATE(scan_date)",$json_data->scan_date);
                    $orders = $this->db->get("ups_scan_dates")->result();
                    $data_arr = [];
                        if($orders){                  
                                foreach($orders as $order){
                                    $data_arr[] = $order->order_id; 
                                   // $value = $redis->get($json_data->scan_date);
                                }
                                if($data_arr){ //değer var ise
                                    $redis->setex("scan_date_".$json_data->scan_date, 10 ,json_encode($data_arr));
                                }
                                //başarılı istek sonucu-from database
                                echo json_encode(array("status"=>"success","database"=>"mysql","message"=>json_encode($data_arr)));
                                exit;
                            
                        }else{
                            echo json_encode(array("status"=>"error","message"=>"şuanda böyle bir veri bulunmuyor"));
                            exit;
                        }
                }
                
            }
            else{
                $name = "test_method";
                $this->file_log($json_data->scan_date,$name); 
                echo json_encode(array("status"=>"error","message"=>"veri alınamadı"));
                exit;
            }
        }catch (\Throwable $th) {
            $this->file_log($th); 
            echo json_encode(array("status"=>"error","message"=>"bir hata oluştu"));
            exit;
        }
    }
}
