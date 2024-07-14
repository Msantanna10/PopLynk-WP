<?php
/**
 * Custom admin login
 */
add_action( 'login_enqueue_scripts', 'customize_admin_login' );
function customize_admin_login() { ?>
  <style>
    a, a:hover, a:focus, h1, h1:focus, div, *, *:focus {
      outline: none !important;
      text-decoration: none !important
    }
    .wp-core-ui .button-primary {
      background: #758cc3 !important;
      border-color: #758cc3 !important
    }
  
    /* Labels name and password */
    body.login {
      background-color: transparent;
      background-image: linear-gradient(223deg, rgba(139,81,186,1) 26%, rgba(111,25,182,1) 64%);
    }
    body.login div#login form#loginform p label { 
      color: #000; 
    }
  
    /* Wordpress Logo */
    .login h1 {
      background-color:transparent !important;
      padding: 10px !important;
      border-radius: 10px !important;
    }
    .login h1 a {
      opacity: 1 !important;
      background-image: url('<?php echo get_template_directory_uri(); ?>/img/logo-big.webp') !important;
      background-size: contain !important;
      width: 100% !important;
      height: 60px !important;
      background-position: center !important;
      margin: 0px auto !important;
      box-shadow: unset;
      filter: brightness(0) invert(1);
    } 
    .login .message, .login #login_error {
      margin: 20px 0px !important
    }
  
    /* Return to site anchor */
    body.login div#login p#backtoblog a { 
      color: #fff; 
    }
    body.login #backtoblog a, .login #nav a {
      color: #fff !important;
      box-shadow: unset !important;
    }
    body.login form {
      border-radius: 5px;
      background-color: rgba(255, 255, 255, 0.9);
    }
    .login .button-primary {
      background-color: #6f19b6 !important;
      border-color: #6f19b6 !important;
    }
    p#backtoblog {
      display: none;
    }
    form ~ p {
      padding: 0px !important;
      text-align: center;
    }
  </style>

<?php }

/**
 * Custom logo URL
 */
add_filter( 'login_headerurl', 'custom_loginlogo_url' );
function custom_loginlogo_url($url) {
  return home_url();
}