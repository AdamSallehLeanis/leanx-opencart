<?php

define('LEANX_DB_PREFIX', 'payment_leanx_');

class ControllerExtensionPaymentLeanx extends Controller
{

	private $error = array();

	public function index()
	{

		$this->load->language('extension/payment/leanx');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ($this->validate($data)) {
			$this->model_setting_setting->editSetting('payment_leanx', $this->request->post);
			$data['save_success'] = true;
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		$data['breadcrumbs'] = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
				'separator' => false
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/payment/leanx', 'user_token=' . $this->session->data['user_token'], true),
			)
		);

		$data['action'] = $this->url->link('extension/payment/leanx', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/payment/leanx', $data));

	}

	protected function validate(&$data)
	{
		$valid = false;
		$names = $this->uniquify_array(['status', 'sort_order', 'auth_token', 'collection_uuid', 'is_sandbox', 'hash_key']);
		$required_names = $this->uniquify_array(['auth_token', 'collection_uuid', 'hash_key']);

		foreach ($names as $name) {
			$data[$name] = $this->config->get($name);
		}
		foreach ($required_names as $name) {
			$error_key = 'error_' . str_replace(LEANX_DB_PREFIX, '', $name);
			$data[$error_key] = false;
		}

		if (!$this->user->hasPermission('modify', 'extension/payment/leanx')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$valid = true;
			foreach ($names as $name) {
				$data[$name] = $this->request->post[$name]; {
					if (in_array($name, $required_names) && strlen($this->request->post[$name]) == 0) {
						$error_key = 'error_' . str_replace(LEANX_DB_PREFIX, '', $name);
						$data[$error_key] = $this->language->get($error_key);
						$valid = false;
					}
				}
			}

			$auth_token = $this->request->post['payment_leanx_auth_token'];
			$collection_uuid = $this->request->post['payment_leanx_collection_uuid'];
			$is_sandbox = $this->request->post['payment_leanx_is_sandbox'];
			$leanx_api = new LeanxApi($auth_token, $is_sandbox);

			// validate auth token
			list($rheader, $rbody) = $leanx_api->validateAuthToken();
			if ($rbody['response_code'] != 2100 && $rbody['response_code'] != 2000) {
				$data['error_auth_token'] = $this->language->get('error_auth_token_invalidate');
				$valid = false;
			}

			// validate collection id
			list($rheaderColl, $rbodyColl) = $leanx_api->validateCollection($collection_uuid);
			if ($rbodyColl['response_code'] != 2100 && $rbodyColl['response_code'] != 2000) {
				$data['error_collection_uuid'] = $this->language->get('error_collection_uuid_invalidate');
				$valid = false;
			}
		}

		return $valid;
	}

	private function uniquify_array($strs = array())
	{
		$ret = array();
		foreach ($strs as $str) {
			$ret[] = LEANX_DB_PREFIX . $str;
		}
		return $ret;
	}

	public function install()
	{
		$this->load->model('extension/payment/leanx');
		$this->model_extension_payment_leanx->install();
	}

	public function uninstall()
	{
		$this->load->model('extension/payment/leanx');
		$this->model_extension_payment_leanx->uninstall();
	}
}