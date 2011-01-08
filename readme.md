Here is an example subscribe method that you could place in a controller:

	public function newsletter_subscribe() {
		$this->auto_render = FALSE;
		$post = $this->input->post();

		if(!empty($post['newsletter_email'])) {
			$email = $post['newsletter_email'];
		
			$contactAdd = new IContact();
			$result = $contactAdd->subscribeContactToList(array(
				'firstName' => empty($post['newsletter_first_name']) ? '' : $post['newsletter_first_name'],
				'lastName' => empty($post['newsletter_last_name']) ? '' : $post['newsletter_last_name'],
				'email' => $email
			), Kohana::config('ascension.newsletter_list_name'));

			if ($result) {
				echo "subscribed";
			} else {
				echo "error";
			}
		
			Session::instance()->set('subscribed', 'true');
		} else {
			echo "fail";
		}
	}