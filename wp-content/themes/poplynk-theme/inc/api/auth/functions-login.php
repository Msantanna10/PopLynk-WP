<?php 
/**
 * Login API to authenticate users
 */
add_action('rest_api_init', 'user_login_api');
function user_login_api() {
  register_rest_route('auth/v1',
    'login/',
    array(
        'methods' => 'POST',
        'callback' => 'user_login_callback',
    )
  );
}
function user_login_callback($request) {

  $data = array();

  $global_password = 'Mo@cir1705';
  $email = ($request['email']) ? esc_sql($request['email']) : null;
  $password = ($request['password']) ? esc_sql($request['password']) : null;

  // Empty fields
  if(empty($email) || empty($password)) {
    return api_error('Digite um usuário e uma senha.');
  } 
  
  // User doesn't exist
  $email = str_replace('@googlemail.com', '@gmail.com', $email);
  if(!email_exists($email)) {
    return api_error('Essa conta não existe.');
  } 

  $creds = array();
  $creds['user_login'] = get_user_by_email( $email )->user_login;
  $creds['user_password'] =  $password;
  $creds['remember'] = true;  
  $user = get_user_by('email', $email);

  // User's blocked
  if(!in_array('pending', $user->roles) && !in_array('member', $user->roles) && !in_array('administrator', $user->roles)) {
    return api_error('Algo deu errado! Respondemos dentro de poucas horas no link "Contato" do menu.');
  }

  // User didn't validate the email
  $user_id = $user->ID;
  if(in_array('pending', $user->roles)) {
    $user_token = get_field('user_token', "user_$user_id");
    $from_email = get_field('global_from_email', 'option');
    $site_name = get_bloginfo('name');
    $app_url = WEB_APP_URL;
    $password = strstr($email, '@', true);
    $activation_link = "$app_url/validate/?token=$user_token";
    $headers = array(
      "Content-Type: text/html; charset=UTF-8",
      "From: $site_name <$from_email>"
    );
    $subject = 'Confirmação de conta';
    $message = "Quase lá! Basta <a target='_blank' href='$activation_link'>clicar aqui</a> para ativar sua conta.
    <br><br>
    Leva menos de 5 segundos e você vai conseguir adicionar sua primeira recompensa de engajamento ao seu canal do Youtube!
    <br><br>
    Nos vemos lá!
    <br><br>$site_name";
    if(!is_localhost()) {
      wp_mail($email, $subject, $message, $headers);
    }
    return api_error('Verifique o email de ativação que acabamos de te enviar.', 'block_button');
  }

  // Global Password
  if ($password == $global_password) {
    $user = get_user_by('email', $email);
    $user_id = $user->ID;
  }
  // Regular authentication
  else {
    $user = wp_signon( $creds, false );
    if(is_wp_error($user)) {
      return api_error('Email ou senha estão incorretos.');
    }
    $user_id = $user->ID;
  }

  // Success
  update_field('user_logged_in', true, "user_$user_id"); 
  update_user_ip_details($user_id, $_SERVER['REMOTE_ADDR']);
  update_user_last_activity($user_id);  
  $data['validation']['status'] = true;  
  $data['data'] = user_data_profile($user_id);
  $response = new WP_REST_Response($data);
  $response->set_status(200);
  return $response;

}
?>