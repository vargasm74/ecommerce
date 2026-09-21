<?php

class ControladorUsuarios{

	/*=============================================
	REGISTRO DE USUARIO
	=============================================*/

	public function ctrRegistroUsuario(){

		if(isset($_POST["regUsuario"])){

			$nombre = trim((string) $_POST["regUsuario"]);
			$email = strtolower(trim((string) $_POST["regEmail"]));
			$passwordPlano = (string) $_POST["regPassword"];

			$nombreValido = preg_match("/^[\\p{L} .'-]{2,100}$/u", $nombre) === 1;
			$emailValido = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
			$passwordValida = strlen($passwordPlano) >= 8 && strlen($passwordPlano) <= 72;

			if($nombreValido && $emailValido && $passwordValida){

				$tabla = "usuarios";
				$usuarioExistente = ModeloUsuarios::mdlMostrarUsuario($tabla, "email", $email);

				if($usuarioExistente){
					echo '<script>
						swal({
							title: "¡ERROR!",
							text: "El correo electrónico ya está registrado.",
							type: "error",
							confirmButtonText: "Cerrar"
						});
					</script>';
					return;
				}

				$emailVerificationEnabled = filter_var(
					getenv("EMAIL_VERIFICATION_ENABLED") ?: "false",
					FILTER_VALIDATE_BOOLEAN
				);

				$encriptar = password_hash($passwordPlano, PASSWORD_DEFAULT);
				$encriptarEmail = $emailVerificationEnabled ? md5($email) : "";

				$datos = array("nombre"=>$nombre,
							   "password"=>$encriptar,
							   "email"=>$email,
							   "foto"=>"",
							   "modo"=>"directo",
							   "verificacion"=>$emailVerificationEnabled ? 1 : 0,
							   "emailEncriptado"=>$encriptarEmail);

				$respuesta = ModeloUsuarios::mdlRegistroUsuario($tabla, $datos);

				if($respuesta == "ok"){

					if(!$emailVerificationEnabled){

						echo '<script>
							swal({
								title: "¡OK!",
								text: "Cuenta creada correctamente. Ya puede iniciar sesión.",
								type: "success",
								confirmButtonText: "Cerrar"
							});
						</script>';

						return;
					}

					/*=============================================
					VERIFICACIÓN CORREO ELECTRÓNICO
					=============================================*/

					date_default_timezone_set("America/Argentina/Buenos_Aires");

					$url = Ruta::ctrRuta();	

					$mail = new PHPMailer;

					$mail->CharSet = 'UTF-8';

					$mail->isMail();

					$mail->setFrom('cursos@tutorialesatualcance.com', 'Tutoriales a tu Alcance');

					$mail->addReplyTo('cursos@tutorialesatualcance.com', 'Tutoriales a tu Alcance');

					$mail->Subject = "Por favor verifique su dirección de correo electrónico";

					$mail->addAddress($email);

					$mail->msgHTML('<div style="width:100%; background:#eee; position:relative; font-family:sans-serif; padding-bottom:40px">
						
						<center>
							
							<img style="padding:20px; width:10%" src="http://tutorialesatualcance.com/tienda/logo.png">

						</center>

						<div style="position:relative; margin:auto; width:600px; background:white; padding:20px">
						
							<center>
							
							<img style="padding:20px; width:15%" src="http://tutorialesatualcance.com/tienda/icon-email.png">

							<h3 style="font-weight:100; color:#999">VERIFIQUE SU DIRECCIÓN DE CORREO ELECTRÓNICO</h3>

							<hr style="border:1px solid #ccc; width:80%">

							<h4 style="font-weight:100; color:#999; padding:0 20px">Para comenzar a usar su cuenta de Tienda Virtual, debe confirmar su dirección de correo electrónico</h4>

							<a href="'.$url.'verificar/'.$encriptarEmail.'" target="_blank" style="text-decoration:none">

							<div style="line-height:60px; background:#0aa; width:60%; color:white">Verifique su dirección de correo electrónico</div>

							</a>

							<br>

							<hr style="border:1px solid #ccc; width:80%">

							<h5 style="font-weight:100; color:#999">Si no se inscribió en esta cuenta, puede ignorar este correo electrónico y la cuenta se eliminará.</h5>

							</center>

						</div>

					</div>');

					$envio = $mail->Send();

					if(!$envio){

						echo '<script> 

							swal({
								  title: "¡ERROR!",
								  text: "¡Ha ocurrido un problema enviando verificación de correo electrónico a '.$email.$mail->ErrorInfo.'!",
								  type:"error",
								  confirmButtonText: "Cerrar",
								  closeOnConfirm: false
								},

								function(isConfirm){

									if(isConfirm){
										history.back();
									}
							});

						</script>';

					}else{

						echo '<script> 

							swal({
								  title: "¡OK!",
								  text: "¡Por favor revise la bandeja de entrada o la carpeta de SPAM de su correo electrónico '.$email.' para verificar la cuenta!",
								  type:"success",
								  confirmButtonText: "Cerrar",
								  closeOnConfirm: false
								},

								function(isConfirm){

									if(isConfirm){
										history.back();
									}
							});

						</script>';

					}

				}

			}else{

				echo '<script> 

						swal({
							  title: "¡ERROR!",
							  text: "Revise nombre, email y contraseña. La contraseña debe tener entre 8 y 72 caracteres.",
							  type:"error",
							  confirmButtonText: "Cerrar",
							  closeOnConfirm: false
							},

							function(isConfirm){

								if(isConfirm){
									history.back();
								}
						});

				</script>';

			}

		}

	}

	/*=============================================
	MOSTRAR USUARIO
	=============================================*/

	static public function ctrMostrarUsuario($item, $valor){

		$tabla = "usuarios";

		$respuesta = ModeloUsuarios::mdlMostrarUsuario($tabla, $item, $valor);

		return $respuesta;

	}

	/*=============================================
	ACTUALIZAR USUARIO
	=============================================*/

	static public function ctrActualizarUsuario($id, $item, $valor){

		$tabla = "usuarios";

		$respuesta = ModeloUsuarios::mdlActualizarUsuario($tabla, $id, $item, $valor);

		return $respuesta;

	}

	/*=============================================
	INGRESO DE USUARIO
	=============================================*/

	public function ctrIngresoUsuario(){

		if(isset($_POST["ingEmail"])){

			$emailIngreso = strtolower(trim((string) $_POST["ingEmail"]));
			$passwordIngreso = (string) $_POST["ingPassword"];

			if(filter_var($emailIngreso, FILTER_VALIDATE_EMAIL) !== false && $passwordIngreso !== ""){

				$tabla = "usuarios";
				$item = "email";
				$valor = $emailIngreso;

				$respuesta = ModeloUsuarios::mdlMostrarUsuario($tabla, $item, $valor);

				$passwordValida = is_array($respuesta)
					&& isset($respuesta["password"])
					&& password_verify($passwordIngreso, $respuesta["password"]);

				if($passwordValida){

					if(password_needs_rehash($respuesta["password"], PASSWORD_DEFAULT)){

						$nuevoHash = password_hash($passwordIngreso, PASSWORD_DEFAULT);

						ModeloUsuarios::mdlActualizarUsuario(
							$tabla,
							$respuesta["id"],
							"password",
							$nuevoHash
						);

					}

					if($respuesta["verificacion"] == 1){

						echo'<script>

							swal({
								  title: "¡NO HA VERIFICADO SU CORREO ELECTRÓNICO!",
								  text: "¡Por favor revise la bandeja de entrada o la carpeta de SPAM de su correo para verififcar la dirección de correo electrónico '.$respuesta["email"].'!",
								  type: "error",
								  confirmButtonText: "Cerrar",
								  closeOnConfirm: false
							},

							function(isConfirm){
									 if (isConfirm) {	   
									    history.back();
									  } 
							});

							</script>';

					}else{

						if(session_status() !== PHP_SESSION_ACTIVE){
							session_start();
						}

						session_regenerate_id(true);

						$_SESSION["validarSesion"] = "ok";
						$_SESSION["id"] = $respuesta["id"];
						$_SESSION["nombre"] = $respuesta["nombre"];
						$_SESSION["foto"] = $respuesta["foto"];
						$_SESSION["email"] = $respuesta["email"];
						$_SESSION["modo"] = $respuesta["modo"];

						echo '<script>
							
							window.location = localStorage.getItem("rutaActual");

						</script>';

					}

				}else{

					echo'<script>

							swal({
								  title: "¡ERROR AL INGRESAR!",
								  text: "¡Por favor revise que el email exista o la contraseña coincida con la registrada!",
								  type: "error",
								  confirmButtonText: "Cerrar",
								  closeOnConfirm: false
							},

							function(isConfirm){
									 if (isConfirm) {	   
									    window.location = localStorage.getItem("rutaActual");
									  } 
							});

							</script>';

				}

			}else{

				echo '<script> 

						swal({
							  title: "¡ERROR!",
							  text: "¡Error al ingresar al sistema, no se permiten caracteres especiales!",
							  type:"error",
							  confirmButtonText: "Cerrar",
							  closeOnConfirm: false
							},

							function(isConfirm){

								if(isConfirm){
									history.back();
								}
						});

				</script>';

			}

		}

	}

	/*=============================================
	OLVIDO DE CONTRASEÑA
	=============================================*/

	public function ctrOlvidoPassword(){

		if(isset($_POST["passEmail"])){

			if(preg_match('/^[^0-9][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[@][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[.][a-zA-Z]{2,4}$/', $_POST["passEmail"])){

				/*=============================================
				GENERAR CONTRASEÑA ALEATORIA
				=============================================*/

				function generarPassword($longitud){

					$key = "";
					$pattern = "1234567890abcdefghijklmnopqrstuvwxyz";

					$max = strlen($pattern)-1;

					for($i = 0; $i < $longitud; $i++){

						$key .= $pattern[random_int(0, $max)];

					}

					return $key;

				}

				$nuevaPassword = generarPassword(11);

				$encriptar = password_hash($nuevaPassword, PASSWORD_DEFAULT);

				$tabla = "usuarios";

				$item1 = "email";
				$valor1 = $_POST["passEmail"];

				$respuesta1 = ModeloUsuarios::mdlMostrarUsuario($tabla, $item1, $valor1);

				if($respuesta1){

					$id = $respuesta1["id"];
					$item2 = "password";
					$valor2 = $encriptar;

					$respuesta2 = ModeloUsuarios::mdlActualizarUsuario($tabla, $id, $item2, $valor2);

					if($respuesta2  == "ok"){

						/*=============================================
						CAMBIO DE CONTRASEÑA
						=============================================*/

						date_default_timezone_set("America/Bogota");

						$url = Ruta::ctrRuta();	

						$mail = new PHPMailer;

						$mail->CharSet = 'UTF-8';

						$mail->isMail();

						$mail->setFrom('cursos@tutorialesatualcance.com', 'Tutoriales a tu Alcance');

						$mail->addReplyTo('cursos@tutorialesatualcance.com', 'Tutoriales a tu Alcance');

						$mail->Subject = "Solicitud de nueva contraseña";

						$mail->addAddress($_POST["passEmail"]);

						$mail->msgHTML('<div style="width:100%; background:#eee; position:relative; font-family:sans-serif; padding-bottom:40px">
	
								<center>
									
									<img style="padding:20px; width:10%" src="http://tutorialesatualcance.com/tienda/logo.png">

								</center>

								<div style="position:relative; margin:auto; width:600px; background:white; padding:20px">
								
									<center>
									
									<img style="padding:20px; width:15%" src="http://tutorialesatualcance.com/tienda/icon-pass.png">

									<h3 style="font-weight:100; color:#999">SOLICITUD DE NUEVA CONTRASEÑA</h3>

									<hr style="border:1px solid #ccc; width:80%">

									<h4 style="font-weight:100; color:#999; padding:0 20px"><strong>Su nueva contraseña: </strong>'.$nuevaPassword.'</h4>

									<a href="'.$url.'" target="_blank" style="text-decoration:none">

									<div style="line-height:60px; background:#0aa; width:60%; color:white">Ingrese nuevamente al sitio</div>

									</a>

									<br>

									<hr style="border:1px solid #ccc; width:80%">

									<h5 style="font-weight:100; color:#999">Si no se inscribió en esta cuenta, puede ignorar este correo electrónico y la cuenta se eliminará.</h5>

									</center>

								</div>

							</div>');

						$envio = $mail->Send();

						if(!$envio){

							echo '<script> 

								swal({
									  title: "¡ERROR!",
									  text: "¡Ha ocurrido un problema enviando cambio de contraseña a '.$_POST["passEmail"].$mail->ErrorInfo.'!",
									  type:"error",
									  confirmButtonText: "Cerrar",
									  closeOnConfirm: false
									},

									function(isConfirm){

										if(isConfirm){
											history.back();
										}
								});

							</script>';

						}else{

							echo '<script> 

								swal({
									  title: "¡OK!",
									  text: "¡Por favor revise la bandeja de entrada o la carpeta de SPAM de su correo electrónico '.$_POST["passEmail"].' para su cambio de contraseña!",
									  type:"success",
									  confirmButtonText: "Cerrar",
									  closeOnConfirm: false
									},

									function(isConfirm){

										if(isConfirm){
											history.back();
										}
								});

							</script>';

						}

					}

				}else{

					echo '<script> 

						swal({
							  title: "¡ERROR!",
							  text: "¡El correo electrónico no existe en el sistema!",
							  type:"error",
							  confirmButtonText: "Cerrar",
							  closeOnConfirm: false
							},

							function(isConfirm){

								if(isConfirm){
									history.back();
								}
						});

					</script>';


				}

			}else{

				echo '<script> 

						swal({
							  title: "¡ERROR!",
							  text: "¡Error al enviar el correo electrónico, está mal escrito!",
							  type:"error",
							  confirmButtonText: "Cerrar",
							  closeOnConfirm: false
							},

							function(isConfirm){

								if(isConfirm){
									history.back();
								}
						});

				</script>';

			}

		}

	}

	/*=============================================
	REGISTRO CON REDES SOCIALES
	=============================================*/

	static public function ctrRegistroRedesSociales($datos){

		$tabla = "usuarios";
		$email = trim((string) ($datos["email"] ?? ""));
		$modo = trim((string) ($datos["modo"] ?? ""));

		if(!filter_var($email, FILTER_VALIDATE_EMAIL) || $modo === ""){
			return "error";
		}

		$usuario = ModeloUsuarios::mdlMostrarUsuario($tabla, "email", $email);

		if($usuario){

			if($usuario["modo"] !== $modo){
				return "modo-incompatible";
			}

		}else{

			$respuesta = ModeloUsuarios::mdlRegistroUsuario($tabla, $datos);

			if($respuesta !== "ok"){
				return "error";
			}

			$usuario = ModeloUsuarios::mdlMostrarUsuario($tabla, "email", $email);
		}

		if(!$usuario || $usuario["modo"] !== $modo){
			return "error";
		}

		Seguridad::iniciarSesion();
		session_regenerate_id(true);

		$_SESSION["validarSesion"] = "ok";
		$_SESSION["id"] = $usuario["id"];
		$_SESSION["nombre"] = $usuario["nombre"];
		$_SESSION["foto"] = $usuario["foto"];
		$_SESSION["email"] = $usuario["email"];
		$_SESSION["modo"] = $usuario["modo"];

		return "ok";
	}

	/*=============================================
	ACTUALIZAR PERFIL
	=============================================*/

	public function ctrActualizarPerfil(){

		if(isset($_POST["editarNombre"]) && isset($_SESSION["id"])){

			$tabla = "usuarios";
			$idUsuario = (int) $_SESSION["id"];
			$usuarioActual = ModeloUsuarios::mdlMostrarUsuario($tabla, "id", $idUsuario);

			if(!$usuarioActual){
				return;
			}

			$nombrePerfil = trim((string) $_POST["editarNombre"]);
			$emailPerfil = strtolower(trim((string) $_POST["editarEmail"]));
			$passwordActual = (string) ($_POST["passwordActual"] ?? "");
			$passwordPerfil = (string) ($_POST["editarPassword"] ?? "");

			if(
				preg_match("/^[\\p{L} .'-]{2,100}$/u", $nombrePerfil) !== 1 ||
				filter_var($emailPerfil, FILTER_VALIDATE_EMAIL) === false ||
				($passwordPerfil !== "" && (strlen($passwordPerfil) < 8 || strlen($passwordPerfil) > 72))
			){
				echo '<script>
					swal({
						title: "ERROR",
						text: "Revise nombre, email y contraseña. La contraseña nueva debe tener entre 8 y 72 caracteres.",
						type: "error",
						confirmButtonText: "Cerrar"
					});
				</script>';
				return;
			}

			if($passwordPerfil !== ""){

				if($passwordActual === "" || !password_verify($passwordActual, $usuarioActual["password"])){

					echo '<script>
						swal({
							title: "ERROR",
							text: "La contraseña actual no coincide. No se realizó el cambio.",
							type: "error",
							confirmButtonText: "Cerrar"
						});
					</script>';

					return;
				}

			}

			$otroUsuario = ModeloUsuarios::mdlMostrarUsuario($tabla, "email", $emailPerfil);

			if($otroUsuario && (int) $otroUsuario["id"] !== $idUsuario){
				echo '<script>
					swal({
						title: "ERROR",
						text: "Ese correo electrónico ya pertenece a otra cuenta.",
						type: "error",
						confirmButtonText: "Cerrar"
					});
				</script>';
				return;
			}

			/*=============================================
			VALIDAR IMAGEN
			=============================================*/

			$ruta = $usuarioActual["foto"];

			if(isset($_FILES["datosImagen"]) && $_FILES["datosImagen"]["error"] !== UPLOAD_ERR_NO_FILE){

				try{

					$archivo = $_FILES["datosImagen"];

				if($archivo["error"] !== UPLOAD_ERR_OK){
					throw new RuntimeException("Error al recibir la imagen");
				}

				$tamanoMaximo = 5 * 1024 * 1024;

				if($archivo["size"] <= 0 || $archivo["size"] > $tamanoMaximo){
					throw new RuntimeException("La imagen supera el tamaño permitido");
				}

				if(!is_uploaded_file($archivo["tmp_name"])){
					throw new RuntimeException("El archivo recibido no es un upload valido");
				}

				$finfo = new finfo(FILEINFO_MIME_TYPE);
				$mimeReal = $finfo->file($archivo["tmp_name"]);

				$tiposPermitidos = array(
					"image/jpeg" => "jpg",
					"image/png" => "png"
				);

				if(!isset($tiposPermitidos[$mimeReal])){
					throw new RuntimeException("Tipo de imagen no permitido");
				}

				$dimensiones = @getimagesize($archivo["tmp_name"]);

				if($dimensiones === false){
					throw new RuntimeException("El archivo no es una imagen valida");
				}

				$ancho = (int) $dimensiones[0];
				$alto = (int) $dimensiones[1];

				if($ancho < 1 || $alto < 1 || $ancho > 5000 || $alto > 5000){
					throw new RuntimeException("Dimensiones de imagen no permitidas");
				}

				$directorioRelativo = "vistas/img/usuarios/".$idUsuario;
				$directorioAbsoluto = dirname(__DIR__)."/".$directorioRelativo;

				if(!is_dir($directorioAbsoluto) && !mkdir($directorioAbsoluto, 0755, true)){
					throw new RuntimeException("No se pudo crear el directorio de imagenes");
				}

				$extension = $tiposPermitidos[$mimeReal];
				$nombreSeguro = bin2hex(random_bytes(16)).".".$extension;
				$rutaNueva = $directorioRelativo."/".$nombreSeguro;
				$rutaNuevaAbsoluta = $directorioAbsoluto."/".$nombreSeguro;

				$origen = $mimeReal === "image/jpeg"
					? @imagecreatefromjpeg($archivo["tmp_name"])
					: @imagecreatefrompng($archivo["tmp_name"]);

				if($origen === false){
					throw new RuntimeException("No se pudo procesar la imagen");
				}

				$nuevoAncho = 500;
				$nuevoAlto = 500;
				$destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

				if($mimeReal === "image/png"){
					imagealphablending($destino, false);
					imagesavealpha($destino, true);
				}

				imagecopyresampled(
					$destino,
					$origen,
					0,
					0,
					0,
					0,
					$nuevoAncho,
					$nuevoAlto,
					$ancho,
					$alto
				);

				$guardada = $mimeReal === "image/jpeg"
					? imagejpeg($destino, $rutaNuevaAbsoluta, 85)
					: imagepng($destino, $rutaNuevaAbsoluta);

				imagedestroy($origen);
				imagedestroy($destino);

				if(!$guardada){
					throw new RuntimeException("No se pudo guardar la imagen");
				}

				if(!empty($usuarioActual["foto"])){

					$fotoAnterior = basename($usuarioActual["foto"]);
					$rutaAnteriorAbsoluta = $directorioAbsoluto."/".$fotoAnterior;

					if(is_file($rutaAnteriorAbsoluta)){
						@unlink($rutaAnteriorAbsoluta);
					}

				}

					$ruta = $rutaNueva;

				}catch(Throwable $e){

					error_log("Upload perfil rechazado para usuario ".$idUsuario.": ".$e->getMessage());

					echo '<script>
						swal({
							title: "ERROR",
							text: "La imagen no pudo ser procesada. Use JPG o PNG de hasta 5 MB.",
							type: "error",
							confirmButtonText: "Cerrar"
						});
					</script>';

					return;

				}

			}

			if($passwordPerfil === ""){

				$password = $usuarioActual["password"];

			}else{

				$password = password_hash($passwordPerfil, PASSWORD_DEFAULT);

			}

			$datos = array("nombre" => $nombrePerfil,
						   "email" => $emailPerfil,
						   "password" => $password,
						   "foto" => $ruta,
						   "id" => $idUsuario);

			$respuesta = ModeloUsuarios::mdlActualizarPerfil($tabla, $datos);

			if($respuesta == "ok"){

				$_SESSION["validarSesion"] = "ok";
				$_SESSION["id"] = $datos["id"];
				$_SESSION["nombre"] = $datos["nombre"];
				$_SESSION["foto"] = $datos["foto"];
				$_SESSION["email"] = $datos["email"];
				$_SESSION["modo"] = $usuarioActual["modo"];

				echo '<script> 

						swal({
							  title: "¡OK!",
							  text: "¡Su cuenta ha sido actualizada correctamente!",
							  type:"success",
							  confirmButtonText: "Cerrar",
							  closeOnConfirm: false
							},

							function(isConfirm){

								if(isConfirm){
									history.back();
								}
						});

				</script>';


			}

		}

	}

	/*=============================================
	MOSTRAR COMPRAS
	=============================================*/

	static public function ctrMostrarCompras($item, $valor){

		$tabla = "compras";

		$respuesta = ModeloUsuarios::mdlMostrarCompras($tabla, $item, $valor);

		return $respuesta;

	}

	/*=============================================
	MOSTRAR COMENTARIOS EN PERFIL
	=============================================*/

	static public function ctrMostrarComentariosPerfil($datos){

		$tabla = "comentarios";

		$respuesta = ModeloUsuarios::mdlMostrarComentariosPerfil($tabla, $datos);

		return $respuesta;

	}


	/*=============================================
	ACTUALIZAR COMENTARIOS
	=============================================*/

	public function ctrActualizarComentario(){

		if(isset($_POST["idComentario"])){

			if(preg_match('/^[,\\.\\a-zA-Z0-9ñÑáéíóúÁÉÍÓÚ ]+$/', $_POST["comentario"])){

				if($_POST["comentario"] != ""){

					$tabla = "comentarios";

					$datos = array("id"=>$_POST["idComentario"],
								   "calificacion"=>$_POST["puntaje"],
								   "comentario"=>$_POST["comentario"]);

					$respuesta = ModeloUsuarios::mdlActualizarComentario($tabla, $datos);

					if($respuesta == "ok"){

						echo'<script>

								swal({
									  title: "¡GRACIAS POR COMPARTIR SU OPINIÓN!",
									  text: "¡Su calificación y comentario ha sido guardado!",
									  type: "success",
									  confirmButtonText: "Cerrar",
									  closeOnConfirm: false
								},

								function(isConfirm){
										 if (isConfirm) {	   
										   history.back();
										  } 
								});

							  </script>';

					}

				}else{

					echo'<script>

						swal({
							  title: "¡ERROR AL ENVIAR SU CALIFICACIÓN!",
							  text: "¡El comentario no puede estar vacío!",
							  type: "error",
							  confirmButtonText: "Cerrar",
							  closeOnConfirm: false
						},

						function(isConfirm){
								 if (isConfirm) {	   
								   history.back();
								  } 
						});

					  </script>';

				}	

			}else{

				echo'<script>

					swal({
						  title: "¡ERROR AL ENVIAR SU CALIFICACIÓN!",
						  text: "¡El comentario no puede llevar caracteres especiales!",
						  type: "error",
						  confirmButtonText: "Cerrar",
						  closeOnConfirm: false
					},

					function(isConfirm){
							 if (isConfirm) {	   
							   history.back();
							  } 
					});

				  </script>';

			}

		}

	}

	/*=============================================
	AGREGAR A LISTA DE DESEOS
	=============================================*/

	static public function ctrAgregarDeseo($datos){

		$tabla = "deseos";

		$respuesta = ModeloUsuarios::mdlAgregarDeseo($tabla, $datos);

		return $respuesta;

	}

	/*=============================================
	MOSTRAR LISTA DE DESEOS
	=============================================*/

	static public function ctrMostrarDeseos($item){

		$tabla = "deseos";

		$respuesta = ModeloUsuarios::mdlMostrarDeseos($tabla, $item);

		return $respuesta;

	}

	/*=============================================
	QUITAR PRODUCTO DE LISTA DE DESEOS
	=============================================*/
	static public function ctrQuitarDeseo($datos){

		$tabla = "deseos";

		$respuesta = ModeloUsuarios::mdlQuitarDeseo($tabla, $datos);

		return $respuesta;

	}

	/*=============================================
	ELIMINAR USUARIO
	=============================================*/

	public static function ctrEliminarUsuario(){

		if(!isset($_SESSION["id"])){
			return "unauthorized";
		}

		$id = (int) $_SESSION["id"];
		$usuario = ModeloUsuarios::mdlMostrarUsuario("usuarios", "id", $id);

		if(!$usuario){
			return "error";
		}

		$respuesta = ModeloUsuarios::mdlEliminarCuentaCompleta($id);

		if($respuesta !== "ok"){
			return "error";
		}

		if($usuario["modo"] === "directo" && !empty($usuario["foto"])){

			$directorioUsuario = dirname(__DIR__)."/vistas/img/usuarios/".$id;
			$archivoFoto = $directorioUsuario."/".basename($usuario["foto"]);

			if(is_file($archivoFoto)){
				@unlink($archivoFoto);
			}

			if(is_dir($directorioUsuario)){
				$archivos = array_diff(scandir($directorioUsuario), array(".", ".."));

				if(count($archivos) === 0){
					@rmdir($directorioUsuario);
				}
			}

		}

		return "ok";

	}
}