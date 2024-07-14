<?php 
/**
 * Register API to create new accounts
 */
add_action('rest_api_init', 'user_registration_api');
function user_registration_api() {
  register_rest_route('auth/v1',
    'register/',
    array(
        'methods' => 'POST',
        'callback' => 'user_registration_callback',
    )
  );
}
function user_registration_callback($request) {

  $data = array();

  $email = ($request['email']) ? esc_sql($request['email']) : null;
  $password = ($request['password']) ? esc_sql($request['password']) : null;

  // Empty fields
  if(empty($email) || empty($password)) {
    return api_error('Preencha todos os campos.');
  }

  // Password length
  if(strlen($password) < 8) {
    return api_error('Sua senha deve conter pelo menos 8 caracteres.');
  }
  
  // Email in use
  if(email_exists($email)) {
    return api_error('Este email já está em uso. Clique em "Já tem uma conta?" abaixo.');
  }

  // Email is valid and it's not in use
  $user_token = bin2hex(random_bytes(60));
  $user_token = $user_token . strstr($email, '@', true);
  $user_token = str_replace(array('-', ' '), '', $user_token);
  $user_token = strtoupper($user_token);
  $user_data = array(
    'user_email' => $email,
    'user_pass' => $password,
    'user_login' => strstr($email, '@', true) . '-' . uniqid(), // everthing before @ + unique ID to be sure
    'user_registered' => current_time('mysql'),
    'role' => 'pending'
  );

  $user_id = wp_insert_user($user_data);

  // Account created without errors
  if(!is_wp_error($user_id)) {
    $from_email = get_field('global_from_email', 'option');
    $site_name = get_bloginfo('name');
    $app_url = WEB_APP_URL;
    $activation_link = "$app_url/login/?validate=$user_token";
    $headers = array(
      "Content-Type: text/html; charset=UTF-8",
      "From: $site_name <$from_email>"
    );
    $to = $email;
    $subject = 'Confirmação de conta';
    $message = "Conta criada com sucesso! Basta <a target='_blank' href='$activation_link'>clicar aqui</a> para ativar sua conta.
    <br><br>
    Leva menos de 5 segundos e você vai conseguir adicionar sua primeira recompensa de engajamento ao seu canal do Youtube!
    <br><br>
    Nos vemos lá!
    <br><br>$site_name";
    $emailSent = (is_localhost()) ? false : wp_mail($to, $subject, $message, $headers);
  }

  // Error
  else {
    return api_error('Algo deu errado! Atualize a página e tente novamente.');
  }

  // Success
  update_field('user_token', $user_token, "user_$user_id");
  update_user_ip_details($user_id, $_SERVER['REMOTE_ADDR']);
  update_user_last_activity($user_id);  
  $data['validation']['status'] = true;
  $response = new WP_REST_Response($data);
  $response->set_status(200);
  return $response;

}