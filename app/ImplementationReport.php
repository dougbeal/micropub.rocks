<?php
namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;
use ORM;
use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;

class ImplementationReport {

  private $user;
  private $endpoint;
  private $client;

  public function get_report(ServerRequestInterface $request, ResponseInterface $response) {
    

    
  }

  private function _check_permissions(&$request, &$response, $source='query') {
    session_setup();

    if(!is_logged_in()) {
      return login_required($response);
    }

    if($source == 'body')
      $params = $request->getParsedBody();
    else
      $params = $request->getQueryParams();
    
    $this->user = logged_in_user();

    // Verify an endpoint is specified and the user has permission to access it
    if(!isset($params['id']) || !isset($params['type']) || !in_array($params['type'], ['client','server']))
      return $response->withHeader('Location', '/dashboard?error='.$params['type'])->withStatus(302);

    if($params['type'] == 'server') {
      $this->endpoint = ORM::for_table('micropub_endpoints')
        ->where('user_id', $this->user->id)
        ->where('id', $params['id'])
        ->find_one();

      if(!$this->endpoint)
        return $response->withHeader('Location', '/dashboard?error=404')->withStatus(302);
    } else {
      $this->client = ORM::for_table('micropub_clients')
        ->where('user_id', $this->user->id)
        ->where('id', $params['id'])
        ->find_one();

      if(!$this->client)
        return $response->withHeader('Location', '/dashboard?error=404')->withStatus(302);
    }

    return null;    
  }

  public static function store_server_feature($endpoint_id, $feature_num, $implements, $test_id) {
    $result = ORM::for_table('feature_results')
      ->where('endpoint_id', $endpoint_id)
      ->where('feature_num', $feature_num)
      ->find_one();

    if(!$result) {
      // New result
      $result = ORM::for_table('feature_results')->create();
      $result->endpoint_id = $endpoint_id;
      $result->feature_num = $feature_num;
      $result->created_at = date('Y-m-d H:i:s');
      $result->implements = $implements;
    } else {
      // Updating a result, only set to fail (-1) if the new result is from the same test
      if($implements == 1) {
        $result->implements = $implements;
      } else {
        if($result->source_test_id == $test_id) {
          $result->implements = $implements;
        }
      }
    }

    $result->source_test_id = $test_id;
    $result->updated_at = date('Y-m-d H:i:s');
    $result->save();
  }

  public function store_result(ServerRequestInterface $request, ResponseInterface $response) {
    if($check = $this->_check_permissions($request, $response, 'body'))
      return $check;

    $params = $request->getParsedBody();

    $col = $params['type'] == 'server' ? 'endpoint_id' : 'client_id';
    $id = $params['id'];

    self::store_server_feature($id, $params['feature_num'], $params['implements'], $params['source_test']);

    return new JsonResponse([
      'result' => 'ok'
    ], 200);

  }

}