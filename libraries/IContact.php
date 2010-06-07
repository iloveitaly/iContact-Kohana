<?php
define('STATUS_CODE_SUCCESS', 200);

/***********************************/
/** set your account details here **/
/***********************************/

/*
$accountId      = 1001413;
$clientFolderId = 9196527;
$listId         = 8485;

$uploadId = createUpload();
uploadData($uploadId, '../data/upload.csv');
$status = getUpload($uploadId);
while ($status != 'complete') {
	$status = getUpload($uploadId);
	sleep(1);
}
*/

class IContact_Core {
	// upload contact list functions
		
	public function uploadContactFile($file) {
		$referenceID = $this->createUploadReference();
		
		if(!$referenceID) return false;
		
		uploadData($referenceID, $file);
		
		while($this->getUploadStatus($referenceID) != 'complete') {
			sleep(1);
		}
		
		return true;
	}
	
	function createUploadReference() {
		global $accountId, $clientFolderId, $listId;

		$uploadId = null;

		$response = $this->callResource("/a/{$accountId}/c/{$clientFolderId}/uploads",
			'POST', array(
				array(
					'action' => 'add',
					'lists'  => array($listId),
				)
			));

		if ($response['code'] == STATUS_CODE_SUCCESS) {
			$uploadId = $response['data']['uploadId'];

			$warningCount = 0;
			if (!empty($response['data']['warnings'])) {
				$warningCount = count($response['data']['warnings']);
			}

			echo "<p>Added upload {$uploadId}, with {$warningCount} warnings.</p>\n";

			dump($response['data']);
		} else {
			Kohana::log('error', 'Error creating upload reference with code: '.$response['code']);
			Kohana::log('error', 'iContact response data: '.print_r($response['data'], true));
			
			return false;
		}

		return $uploadId;
	}
	
	protected function uploadData($uploadId, $file) {
		global $accountId, $clientFolderId;

		$response = $this->callResource("/a/{$accountId}/c/{$clientFolderId}/uploads/{$uploadId}/data", 'PUT', $file);

		if ($response['code'] == STATUS_CODE_SUCCESS) {
			$uploadId = $response['data']['uploadId'];

			$warningCount = 0;
			if (!empty($response['data']['warnings'])) {
				$warningCount = count($response['data']['warnings']);
			}

			echo "<p>Updated upload {$uploadId}, with {$warningCount} warnings.</p>\n";

			dump($response['data']);
		} else {
			echo "<p>Error Code: {$response['code']}</p>\n";

			dump($response['data']);
		}
	}
	
	protected function getUploadStatus($uploadId) {
		global $accountId, $clientFolderId;

		$status = null;
		$response = $this->callResource("/a/{$accountId}/c/{$clientFolderId}/uploads/{$uploadId}", 'GET');

		if ($response['code'] == STATUS_CODE_SUCCESS) {
			$status = $response['data']['status'];

			$warningCount = 0;
			if (!empty($response['data']['warnings'])) {
				$warningCount = count($response['data']['warnings']);
			}

			echo "<p>Added upload {$uploadId}, with {$warningCount} warnings.</p>\n";

			dump($response['data']);
		} else {
			$status = 'complete';

			echo "<p>Error Code: {$response['code']}</p>\n";

			dump($response['data']);
		}

		return $status;
	}
	
	// authentication
	
	public function getAuthInfo() {
		$this->getAccountID();
	}
	
	protected function getAccountID() {
		print_r($this->callResource('/a/', 'GET'));
	}
	
	// list management
	
	public function addList() {
		global $accountId, $clientFolderId, $welcomeMessageId;

		$listId = null;

		$response = callResource("/a/{$accountId}/c/{$clientFolderId}/lists",
			'POST', array(
				array(
					'name' => 'my new list',
					'welcomeMessageId'   => $welcomeMessageId,
					'emailOwnerOnChange' => 0,
					'welcomeOnManualAdd' => 0,
					'welcomeOnSignupAdd' => 0,
				)
			));

		if ($response['code'] == STATUS_CODE_SUCCESS) {
			echo "<h1>Success - Add List</h1>\n";

			$listId = $response['data']['lists'][0]['listId'];

			$warningCount = 0;
			if (!empty($response['data']['warnings'])) {
				$warningCount = count($response['data']['warnings']);
			}

			echo "<p>Added list {$listId}, with {$warningCount} warnings.</p>\n";

			dump($response['data']);
		} else {
			echo "<h1>Error - Add List</h1>\n";

			echo "<p>Error Code: {$response['code']}</p>\n";

			dump($response['data']);
		}

		return $listId;
	}
	
	
	protected function callResource($url, $method, $data = null) {
		$url    = Kohana::config('icontact.app_url').$url;
		$handle = new Curl();
		
		// application/xml

		$handle->setopt_array(array(
			CURLOPT_URL => $url,
			CURLOPT_HTTPHEADER => array(
				'Accept: text/xml',
				'Content-Type: text/xml',
				'Api-Version: 2.0',
				'Api-AppId: ' . Kohana::config('icontact.app_id'),
				'Api-Username: ' . Kohana::config('icontact.username'),
				'Api-Password: ' . Kohana::config('icontact.password'),
			),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_POST => FALSE
		));

		switch($method) {
			case 'POST':
				$handle->setopt_array(array(
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => json_encode($data)
				));
			break;
			case 'PUT':
				$handle->setopt_array(array(
					CURLOPT_PUT => true,
					CURLOPT_INFILE => fopen($data, 'r')
				));
			break;
			case 'DELETE':
				$handle->setopt(CURLOPT_CUSTOMREQUEST, 'DELETE');
			break;
		}
		
		$code = $handle->exec(array(STATUS_CODE_SUCCESS));
		$response = $handle->result();

		$response = json_decode($response, true);

		return array(
			'code' => $code,
			'data' => $response,
		);
	}
}
?>