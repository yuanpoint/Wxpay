<?php
namespace Api\Controller;
use Think\Controller;
/**
*微信支付
*/
class WxpayController extends MainController{
	public function _initialize(){
		
		define('APPID','wxda639393b4fab8a5');//应用APPID
		define('MCHID','1430318002');//商户号

		## 获取APIKEY新版微信已经更新APIKEY的获取方式，需要登录到微信支付商户平台配置，在“账户设置”->“[API安全](https://pay.weixin.qq.com/index.php/account/api_cert)”中的**API密钥**下进行设置。
		define('KEY', '4D214FD7927E9783923F80E1CD099F89');//APIKEY

		define('NOTIFY_URL','http://www.adpie.tv/');//支付回调地址
		
		define('ORDER_URL', 'https://api.mch.weixin.qq.com/pay/unifiedorder');//统一下单api地址
	}
	/**
	*统一下单
	*@param int $UID 订单号
	*@param float $total_fee 支付的总金额，单位为分
	*@param string $body 商品描述 例如“广告派-购买商品”
	*@return 
	*/
	public function unifiedOrder($ordercode,$total_fee,$body){
		//校验传值
		if($ordercode=="" || $ordercode==NULL){
			return "订单号不能为空";
		}
		if($total_fee<=0 || $total_fee==""){
			return "金额不能为空";
		}
		if($body=="" || $body==NULL){
			return "商品描述不能为空";
		}
		if(APPID==""){
			return "APPID不能为空";
		}
		if(MCHID==""){
			return "MCHID不能为空";
		}
		if(NOTIFY_URL==""){
			return "NOTIFY_URL不能为空";
		}
		// return $this->get_clientIp();
		//构建发送数据包数组
		$arr = array(
			'appid'=>APPID,
			'mch_id'=>MCHID,
			'nonce_str'=>$this->getNonceStr(),
			'out_trade_no'=>$ordercode,//商户订单号
			'total_fee'=>$total_fee,
			'spbill_create_ip'=>$this->get_clientIp(),//用户端实际IP
			'body'=>$body,//商品描述
			'time_start'=>$this->getMillisecond(),//交易开始时间
			'notify_url'=>NOTIFY_URL,//接收微信支付异步通知回调地址，通知URL必须为直接可访问的URL，不能携带参数
			'trade_type'=>'APP',//支付类型APP
			'limit_pay'=>'no_credit'//指定不能使用信用卡支付
		);
		//生成签名
		$arr['sign']=$this->MakeSign($arr);
		//构建xml数据包
		$str = $this->ToXml($arr);
		//发送数据包
		$responst = $this->postXmlCurl($str,ORDER_URL);	

		//将返回的数据包转换为数组
		$result = $this->xmlToArr($responst);
		if(!is_array($result)){
			return $result;
		}
		// 统一下单接口返回正常的prepay_id，再按签名规范重新生成签名后，将数据传输给APP。
		// 参与签名的字段名为appId，partnerId，prepayId，nonceStr，timeStamp，package。注意：package的值格式为Sign=WXPay
		$time_stamp = time();
		$pack = 'Sign=WXPay';
		//构建参数列表
		$prePayParams =array();
		$prePayParams['appid']		=$result['appid'];
		$prePayParams['partnerid']	=$result['mch_id'];
		$prePayParams['prepayid']	=$result['prepay_id'];
		$prePayParams['noncestr']	=$result['nonce_str'];
		$prePayParams['package']	=$pack;
		$prePayParams['timestamp']	=$time_stamp;
		//从新生成签名
		$prePayParams['sign']=$this->MakeSign($prePayParams);
		//返回数组
		return $prePayParams;
	}
	/**
	* 生成签名
	* @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	* @ Array $array 所有数值的数组
	*/
	public function MakeSign($array){
		//签名步骤一：按字典序排序参数
		ksort($array);
		//格式化参数格式化成url参数
		$buff = "";
		foreach ($array as $k => $v)
		{
			if($k != "sign" && $v != "" && !is_array($v)){
				$buff .= $k . "=" . $v . "&";
			}
		}
		$string = trim($buff, "&");
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".KEY;
		//签名步骤三：MD5加密
		$string = md5($string);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}
	/**
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public static function getNonceStr($length = 32) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return $str;
	}
	/**
	* 输出xml字符
	*$array 数组
	*
	**/
	public function ToXml($array){
		if(!is_array($array) || count($array) <= 0){

    		return "数组数据异常！";
    	}
    	
    	$xml = "<xml>";
    	foreach ($array as $key=>$val){
    		if (is_numeric($val)){
    			$xml.="<".$key.">".$val."</".$key.">";
    		}else{
    			$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
    		}
        }
        $xml.="</xml>";
        return $xml; 
	}
	/**
	 * 获取毫秒级别的时间戳
	 */
	private static function getMillisecond()
	{
		//获取毫秒的时间戳
		$time = explode ( " ", microtime () );
		$time = $time[1] . ($time[0] * 1000);
		$time2 = explode( ".", $time );
		$time = $time2[0];
		return $time;
	}
	/*
	*获取客户端IP
	*/
	public function get_clientIp(){
		$str = $_SERVER['REMOTE_ADDR'];
		return $str;
	}
	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	private static function postXmlCurl($xml, $url, $second = 30)
	{		
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);

		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);//不校验CA证书
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
		$data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			return "出错了，curl错误代码为".$error;
		}
	}
	/**
    * 将xml转为array
    * @param string $xml
    * @return
    */
	public function xmlToArr($xml){			
        //禁止引用外部xml实体
		libxml_disable_entity_loader(true);
		//将XML转为array
        $arr = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);	
		//fix bug 2015-06-29
		if( $arr['return_code'] != 'SUCCESS'){
			 return "通信失败";
		}
		//检查签名是否存在
		if(!array_key_exists('sign',$arr)){
			return "签名不存在";
		}
		return $arr;
	}
	//调用实例
	public function test(){
		// 获取支付金额
		// $amount='';
		// if($_SERVER['REQUEST_METHOD']=='POST'){
		//     $amount=$_POST['total'];
		// }else{
		//     $amount=$_GET['total'];
		// }
		$total = 0.01;
		$total = floatval($amount);
		$total = round($total*100); // 将元转成分
		if(empty($total)){
		    $total = 100;
		}

		// 商品名称
		$subject = '广告派--商品支付';
		// $res = $this->unifiedOrder(124,$total,$subject);//调用生成APP需要的参数
		// dump($res);
	}

}
































?>