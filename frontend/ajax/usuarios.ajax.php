<?php

require_once "../modelos/seguridad.php";
Seguridad::iniciarSesion();
Seguridad::exigirCsrf();

require_once "../controladores/usuarios.controlador.php";
require_once "../modelos/usuarios.modelo.php";

class AjaxUsuarios{

	/*=============================================
	VALIDAR EMAIL EXISTENTE
	=============================================*/	

	public $validarEmail;

	public function ajaxValidarEmail(){

		$datos = $this->validarEmail;

		$respuesta = ControladorUsuarios::ctrMostrarUsuario("email", $datos);

		echo json_encode($respuesta);

	}

	/*=============================================
	REGISTRO CON FACEBOOK
	=============================================*/

	public $email;
	public $nombre;
	public $foto;

	public function ajaxRegistroFacebook(){

		$datos = array("nombre"=>$this->nombre,
					   "email"=>$this->email,
					   "foto"=>$this->foto,
					   "password"=>"null",
					   "modo"=>"facebook",
					   "verificacion"=>0,
					   "emailEncriptado"=>"null");

		$respuesta = ControladorUsuarios::ctrRegistroRedesSociales($datos);

		echo $respuesta;

	}	

	/*=============================================
	AGREGAR A LISTA DE DESEOS
	=============================================*/	

	public $idProducto;

	public function ajaxAgregarDeseo(){

		if(!isset($_SESSION["id"])){
			http_response_code(401);
			echo "unauthorized";
			return;
		}

		$datos = array("idUsuario"=>(int) $_SESSION["id"],
					   "idProducto"=>(int) $this->idProducto);

		$respuesta = ControladorUsuarios::ctrAgregarDeseo($datos);

		echo $respuesta;

	}

	/*=============================================
	QUITAR PRODUCTO DE LISTA DE DESEOS
	=============================================*/

	public $idDeseo;	

	public function ajaxQuitarDeseo(){

		if(!isset($_SESSION["id"])){
			http_response_code(401);
			echo "unauthorized";
			return;
		}

		$datos = array(
			"idDeseo" => (int) $this->idDeseo,
			"idUsuario" => (int) $_SESSION["id"]
		);

		$respuesta = ControladorUsuarios::ctrQuitarDeseo($datos);

		echo $respuesta;

	}


}

/*=============================================
VALIDAR EMAIL EXISTENTE
=============================================*/	

if(isset($_POST["validarEmail"])){

	$valEmail = new AjaxUsuarios();
	$valEmail -> validarEmail = $_POST["validarEmail"];
	$valEmail -> ajaxValidarEmail();

}

/*=============================================
REGISTRO CON FACEBOOK
=============================================*/


if(isset($_POST["email"])){

	$regFacebook = new AjaxUsuarios();
	$regFacebook -> email = $_POST["email"];
	$regFacebook -> nombre = $_POST["nombre"];
	$regFacebook -> foto = $_POST["foto"];
	$regFacebook -> ajaxRegistroFacebook();

}

/*=============================================
AGREGAR A LISTA DE DESEOS
=============================================*/	

if(isset($_POST["idProducto"])){

	$deseo = new AjaxUsuarios();
	$deseo -> idProducto = $_POST["idProducto"];
	$deseo ->ajaxAgregarDeseo();
}

/*=============================================
QUITAR PRODUCTO DE LISTA DE DESEOS
=============================================*/

if(isset($_POST["idDeseo"])){

	$quitarDeseo = new AjaxUsuarios();
	$quitarDeseo -> idDeseo = $_POST["idDeseo"];
	$quitarDeseo ->ajaxQuitarDeseo();
}


/*=============================================
ELIMINAR CUENTA
=============================================*/

if(isset($_POST["eliminarCuenta"])){

	$respuesta = ControladorUsuarios::ctrEliminarUsuario();

	if($respuesta === "unauthorized"){
		http_response_code(401);
	}

	echo $respuesta;
}
