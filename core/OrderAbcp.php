<?
namespace core;
class OrderAbcp extends Abcp{
	public $param;
	private $basketContent;
	public function __construct($db, $store_id){
		parent::__construct(NULL, $db);
		$this->param = parent::$params[$store_id];
	}
	public function addToBasket($params){
		// debug($params); exit();
		$res = parent::getPostData(
			"{$this->param['url']}/basket/add",
			[
				'userlogin' => $this->param['userlogin'],
				'userpsw' => md5($this->param['userpsw']),
				'positions' => [
					0 => [
						'brand' => $params['brand'],
						'number' => $params['number'],
						'supplierCode' => $params['supplierCode'],
						'itemKey' => $params['itemKey'],
						'quantity' => $params['quantity']
					]
				]
			]
		);
		$array = json_decode($res, true);
		if ($array['positions'][0]['errorMessage']) die ("Произошла ошибка: {$array['positions'][0]['errorMessage']} <a href='{$_SERVER['HTTP_REFERER']}'>Назад</a>");
		$status_id = $params['quantity'] ? 7 : 5;
		$this->db->query("
			UPDATE 
				#orders_values 
			SET 
				`status_id`= $status_id,
				`ordered` = {$params['quantity']}
			WHERE 
				`order_id`={$params['order_id']} AND
				`store_id`={$params['store_id']} AND
				`item_id`={$params['item_id']}
		", '');
		$act = $params['quantity'] ? '+' : '-';
		$this->db->query("
			UPDATE
				#users
			SET
				`reserved_funds`=`reserved_funds` $act {$params['price']} * {$params['quantity']}
			WHERE
				`id`={$params['user_id']}
		", '');
	}
	public function getItemInfoByArticleAndBrend($array){
		$article = Armtek::getComparableString($array['article']);
		$distributorId = str_replace($this->param['title'].'-', '', $array['providerStore']);
		$url =  "{$this->param['url']}/search/articles/?userlogin={$this->param['userlogin']}&userpsw=".md5($this->param['userpsw'])."&useOnlineStocks=1&number={$article}&brand={$array['brend']}";
		$url = str_replace(' ', '%20', $url);
		$response = file_get_contents($url);
		$items = json_decode($response, true);
		if (empty($items)) return false;
		foreach($items as $value){
			if (
				Armtek::getComparableString($value['numberFix']) == Armtek::getComparableString($article) 
				&& $value['distributorId'] == $distributorId
				// && Armtek::getComparableString($value['brand']) == Armtek::getComparableString($brend)
			) {
				return[
					'brand' => $value['brand'],
					'number' => $value['number'],
					'supplierCode' => $value['supplierCode'],
					'itemKey' => $value['itemKey']
				];
			}
		}
		return false;
	}
	private function setBasketContent(){
		$url =  "{$this->param['url']}/basket/content/?userlogin={$this->param['userlogin']}&userpsw=".md5($this->param['userpsw']);
		$response = file_get_contents($url);
		return $this->basketContent = json_decode($response, true);
	}
	public static function getGetString($param){
		return "
			/admin/?view=orders
			&id={$param['order_id']}
			&item_id={$param['item_id']}
			&article={$param['article']}
			&brend={$param['brend']}
			&store_id={$param['store_id']}
			&quan={$param['quan']}
			&price={$param['price']}
			&user_id={$param['user_id']}
			&provider_id={$param['provider_id']}
			&providerStore={$param['providerStore']}
		";
	}
	public function isInBasket($brend, $article){
		if (!$this->basketContent) $this->setBasketContent();
		if (!$this->basketContent) return false;
		foreach($this->basketContent as $value){
			if (
				Armtek::getComparableString($value['numberFix']) == Armtek::getComparableString($article) 
				// && Armtek::getComparableString($value['brand']) == Armtek::getComparableString($brend)
			) return true;
		}
		return false;
	}
	private function getShipmentDate(){
		if ($this->param['provider_id'] == 13) return '';
		$url =  "{$this->param['url']}/basket/shipmentDates/?userlogin={$this->param['userlogin']}&userpsw=".md5($this->param['userpsw']);
		$response = file_get_contents($url);
		$res = json_decode($response, true);
		return $res[1]['date'];
	}
	/**
	 * gets order value by brend and article
	 * @param  [array] $params article, brend - is required, provider_id, status_id - optional
	 * @return [array]  row from orders_values
	 */
	public function getOrderValueByBrendAndArticle($params){
		$article = article_clear($params['article']);
		$where = '';
		if (isset($params['provider_id'])) {
			$where .= " AND ps.provider_id = {$params['provider_id']}";
		}
		if (isset($params['status_id'])) {
			$where .= " AND ov.status_id = {$params['status_id']}";
		}
		$query = "
			SELECT
				ov.*
			FROM
				#orders_values ov
			LEFT JOIN
				#items i ON i.id = ov.item_id
			LEFT JOIN
				#brends b ON b.id = i.brend_id
			LEFT JOIN 
				#provider_stores ps ON ps.id = ov.store_id
			WHERE
				i.article = '$article' AND b.title LIKE '%{$params['brend']}%'
				$where
		";
		$res = $this->db->query($query, '');
		return $res->fetch_assoc();

	}
	public function basketOrder(){
		// exit();
		// $shipmentDate = $this->getShipmentDate();
		// $res = parent::getPostData(
		// 	"{$this->param['url']}/basket/order",
		// 	[
		// 		'userlogin' => $this->param['userlogin'],
		// 		'userpsw' => md5($this->param['userpsw']),
		// 		'paymentMethod' => $this->param['paymentMethod'],
		// 		'shipmentAddress' => $this->param['shipmentAddress'],
		// 		'shipmentOffice' => isset($this->param['shipmentOffice']) ? $this->param['shipmentOffice'] : '',
		// 		'shipmentMethod' => isset($this->param['shipmentMethod']) ? $this->param['shipmentMethod'] : '',
		// 		'shipmentDate' => $shipmentDate
		// 	]
		// );
		$res = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/core/test.rossko.txt');

		if (!$res) die("Ошибка отправления заказка");
		$array = json_decode($res, true);

		foreach($array['orders'] as $key => $order){
			$orderValue = new OrderValue($this->db);
			foreach($order['positions'] as $pos){
				$ov = $this->getOrderValueByBrendAndArticle([
					'brend' => $pos['brend'],
					'article' => $pos['numberFix'],
					'provider_id' => $this->param['provider_id'],
					'status_id' => 7
				]);
				$orderValue->changeStatus(11, $ov);
			}
		}
	}
}

