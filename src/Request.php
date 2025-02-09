<?php
/**
 * @author lin <465382251@qq.com>
 * */

namespace Lin\Bybit;

use GuzzleHttp\Exception\RequestException;
use Lin\Bybit\Exceptions\Exception;

class Request
{
    protected $key='';

    protected $secret='';

    protected $host='';



    protected $nonce='';

    protected $signature='';

    protected $headers=[];

    protected $type='';

    protected $path='';

    protected $data=[];

    protected $options=[];

    public function __construct(array $data)
    {
        $this->key=$data['key'] ?? '';
        $this->secret=$data['secret'] ?? '';
        $this->host=$data['host'] ?? 'https://api.bybit.com';

        $this->options=$data['options'] ?? [];
    }

    /**
     * 认证
     * */
    protected function auth(){
        $this->nonce();

        $this->signature();

        $this->headers();

        $this->options();
    }

    /*
     *
     * */
    protected function nonce(){
        $this->nonce=floor(microtime(true) * 1000);
    }

    /*
     *
     * */
    protected function signature(){
        if(!empty($this->key) && !empty($this->secret)){
            $this->data['api_key']=$this->key;
            $this->data['timestamp']=$this->nonce;

            ksort($this->data);

            $temp=$this->data;
            foreach ($temp as $k=>$v) if(is_bool($v)) $temp[$k]=$v?'true':'false';

            $this->signature = hash_hmac('sha256', urldecode(http_build_query($temp)), $this->secret);
        }
    }

    /*
     *
     * */
    protected function headers(){
        $this->headers=[

        ];
    }

    /*
     *
     * */
    protected function options(){
        if(isset($this->options['headers'])) $this->headers=array_merge($this->headers,$this->options['headers']);

        $this->options['headers']=$this->headers;
        $this->options['timeout'] = $this->options['timeout'] ?? 60;

        if(isset($this->options['proxy']) && $this->options['proxy']===true) {
            $this->options['proxy']=[
                'http'  => 'http://127.0.0.1:12333',
                'https' => 'http://127.0.0.1:12333',
                'no'    =>  ['.cn']
            ];
        }
    }

    /*
     *
     * */
    protected function send(){
        $client = new \GuzzleHttp\Client();
        $url=$this->host.$this->path;

        if($this->type=='GET'||$this->type=='DELETE') $url.= '?'.http_build_query($this->data).($this->signature!=''?'&sign='.$this->signature:'');
        else {
            $temp=$this->data;

            foreach ($temp as $k=>$v){
                if($v=='true') $temp[$k]=true;
                if($v=='false') $temp[$k]=false;
            }

            $this->options['form_params'] = array_merge($temp,['sign'=>$this->signature]);

            //$this->options['body']=json_encode();

        }

        $response = $client->request($this->type, $url, $this->options);

        return $response->getBody()->getContents();
    }

    /*
     *
     * */
    protected function exec(){
        $this->auth();

        try {
            return json_decode($this->send(),true);
        }catch (RequestException $e){
            if(method_exists($e->getResponse(),'getBody')){
                $contents=$e->getResponse()->getBody()->getContents();

                $temp = empty($contents) ? [] : json_decode($contents,true);

                if(!empty($temp)) {
                    $temp['_method']=$this->type;
                    $temp['_url']=$this->host.$this->path;
                }else{
                    $temp['_message']=$e->getMessage();
                }
            }else{
                $temp['_message']=$e->getMessage();
            }

            $temp['_httpcode']=$e->getCode();

            throw new Exception(json_encode($temp));
        }
    }
}
