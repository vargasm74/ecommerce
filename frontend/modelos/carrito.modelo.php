<?php

require_once "conexion.php";

class ModeloCarrito{

	/*=============================================
	MOSTRAR TARIFAS
	=============================================*/

	static public function mdlMostrarTarifas($tabla){

		$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");

		$stmt -> execute();

		return $stmt -> fetch();

		$stmt -> close();

		$tmt =null;

	}

	/*=============================================
	PRODUCTO PARA CALCULO DE CHECKOUT
	=============================================*/

	static public function mdlObtenerProductoCheckout($id){

		$stmt = Conexion::conectar()->prepare(
			"SELECT id, titulo, tipo, precio, precioOferta, peso FROM productos WHERE id = :id LIMIT 1"
		);

		$stmt->bindValue(":id", $id, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetch();
	}

	/*=============================================
	REGLAS FISCALES ACTIVAS POR PAIS
	=============================================*/

	static public function mdlBuscarReglasFiscales($pais){

		try{

			$stmt = Conexion::conectar()->prepare(
				"SELECT id, pais, region, tipo_producto, porcentaje, fecha_desde, fecha_hasta, descripcion
				 FROM impuestos
				 WHERE pais = :pais
				   AND activo = 1
				   AND fecha_desde <= CURRENT_DATE
				   AND (fecha_hasta IS NULL OR fecha_hasta >= CURRENT_DATE)"
			);

			$stmt->bindValue(":pais", $pais, PDO::PARAM_STR);
			$stmt->execute();

			return $stmt->fetchAll();

		}catch(PDOException $e){

			error_log("Reglas fiscales no disponibles: ".$e->getMessage());
			return array();

		}
	}

	/*=============================================
	VERIFICAR COMPRA DE USUARIO
	=============================================*/

	static public function mdlUsuarioTieneCompra($idUsuario, $idProducto){

		$stmt = Conexion::conectar()->prepare(
			"SELECT id
			 FROM compras
			 WHERE id_usuario = :id_usuario
			   AND id_producto = :id_producto
			 LIMIT 1"
		);

		$stmt->bindValue(":id_usuario", (int) $idUsuario, PDO::PARAM_INT);
		$stmt->bindValue(":id_producto", (int) $idProducto, PDO::PARAM_INT);
		$stmt->execute();

		return (bool) $stmt->fetch();
	}

	/*=============================================
	ADQUIRIR PRODUCTO GRATIS
	=============================================*/

	static public function mdlAdquirirProductoGratis($idUsuario, $idProducto, $email, $detalle){

		$conexion = Conexion::conectar();

		try{

			$conexion->beginTransaction();

			$stmt = $conexion->prepare(
				"SELECT id, precio, precioOferta, ventasGratis, cupoGratis
				 FROM productos
				 WHERE id = :id
				 LIMIT 1
				 FOR UPDATE"
			);
			$stmt->bindValue(":id", $idProducto, PDO::PARAM_INT);
			$stmt->execute();

			$producto = $stmt->fetch();


			if(!$producto){
				throw new RuntimeException("Producto inexistente");
			}

			$precioReal = (float) $producto["precioOferta"] > 0
				? (float) $producto["precioOferta"]
				: (float) $producto["precio"];

			if($precioReal != 0.0){
				throw new RuntimeException("El producto no es gratuito");
			}

			$cupoGratis = $producto["cupoGratis"] === null
				? null
				: (int) $producto["cupoGratis"];

			if($cupoGratis !== null && (int) $producto["ventasGratis"] >= $cupoGratis){
				$conexion->rollBack();
				return "agotado";
			}

			$stmt = $conexion->prepare(
				"SELECT id FROM compras
				 WHERE id_usuario = :id_usuario
				   AND id_producto = :id_producto
				 LIMIT 1"
			);
			$stmt->bindValue(":id_usuario", $idUsuario, PDO::PARAM_INT);
			$stmt->bindValue(":id_producto", $idProducto, PDO::PARAM_INT);
			$stmt->execute();

			if($stmt->fetch()){
				$conexion->rollBack();
				return "existe";
			}

			$stmt = $conexion->prepare(
				"INSERT INTO compras
				 (id_usuario, id_producto, envio, metodo, email, direccion, pais, cantidad, detalle, pago)
				 VALUES
				 (:id_usuario, :id_producto, 0, 'gratis', :email, '', '', 1, :detalle, '0.00')"
			);
			$stmt->bindValue(":id_usuario", $idUsuario, PDO::PARAM_INT);
			$stmt->bindValue(":id_producto", $idProducto, PDO::PARAM_INT);
			$stmt->bindValue(":email", $email, PDO::PARAM_STR);
			$stmt->bindValue(":detalle", $detalle, PDO::PARAM_STR);
			$stmt->execute();

			$stmt = $conexion->prepare(
				"INSERT INTO comentarios (id_usuario, id_producto, calificacion, comentario)
				 SELECT :id_usuario, :id_producto, 0, ''
				 WHERE NOT EXISTS (
					 SELECT 1 FROM comentarios
					 WHERE id_usuario = :id_usuario2
					   AND id_producto = :id_producto2
				 )"
			);
			$stmt->bindValue(":id_usuario", $idUsuario, PDO::PARAM_INT);
			$stmt->bindValue(":id_producto", $idProducto, PDO::PARAM_INT);
			$stmt->bindValue(":id_usuario2", $idUsuario, PDO::PARAM_INT);
			$stmt->bindValue(":id_producto2", $idProducto, PDO::PARAM_INT);
			$stmt->execute();

			$stmt = $conexion->prepare(
				"UPDATE productos
				 SET ventasGratis = ventasGratis + 1
				 WHERE id = :id"
			);
			$stmt->bindValue(":id", $idProducto, PDO::PARAM_INT);
			$stmt->execute();

			$conexion->commit();

			return "ok";

		}catch(Throwable $e){

			if($conexion->inTransaction()){
				$conexion->rollBack();
			}

			error_log("Adquisicion gratuita rechazada: ".$e->getMessage());
			return "error";
		}
	}

	/*=============================================
	CREAR PEDIDO INTERNO
	=============================================*/

	static public function mdlCrearOrderInterna($idUsuario, $resumen, $pais, $region, $fxRate, $totalAmount, $idempotencyKey){

		$conexion = Conexion::conectar();

		try{

			$conexion->beginTransaction();

			$stmt = $conexion->prepare(
				"INSERT INTO orders
				 (user_id, status, source_currency, currency, fx_rate,
				  subtotal_source, tax_source, shipping_source, total_source,
				  total_amount, country, region, payment_provider, idempotency_key)
				 VALUES
				 (:user_id, 'PENDING_PROVIDER', 'USD', 'ARS', :fx_rate,
				  :subtotal_source, :tax_source, :shipping_source, :total_source,
				  :total_amount, :country, :region, 'mercadopago', :idempotency_key)"
			);

			$stmt->bindValue(":user_id", (int) $idUsuario, PDO::PARAM_INT);
			$stmt->bindValue(":fx_rate", (string) $fxRate, PDO::PARAM_STR);
			$stmt->bindValue(":subtotal_source", (string) $resumen["subtotal"], PDO::PARAM_STR);
			$stmt->bindValue(":tax_source", (string) $resumen["impuesto"], PDO::PARAM_STR);
			$stmt->bindValue(":shipping_source", (string) $resumen["envio"], PDO::PARAM_STR);
			$stmt->bindValue(":total_source", (string) $resumen["total"], PDO::PARAM_STR);
			$stmt->bindValue(":total_amount", (string) $totalAmount, PDO::PARAM_STR);
			$stmt->bindValue(":country", $pais, PDO::PARAM_STR);
			$stmt->bindValue(":region", $region, PDO::PARAM_STR);
			$stmt->bindValue(":idempotency_key", $idempotencyKey, PDO::PARAM_STR);
			$stmt->execute();

			$orderId = (int) $conexion->lastInsertId();

			$stmtItem = $conexion->prepare(
				"INSERT INTO order_items
				 (order_id, product_id, title, quantity, unit_price_source,
				  subtotal_source, tax_rate, tax_amount_source, tax_rule_id)
				 VALUES
				 (:order_id, :product_id, :title, :quantity, :unit_price_source,
				  :subtotal_source, :tax_rate, :tax_amount_source, :tax_rule_id)"
			);

			foreach($resumen["productos"] as $producto){

				$stmtItem->bindValue(":order_id", $orderId, PDO::PARAM_INT);
				$stmtItem->bindValue(":product_id", (int) $producto["id"], PDO::PARAM_INT);
				$stmtItem->bindValue(":title", (string) $producto["titulo"], PDO::PARAM_STR);
				$stmtItem->bindValue(":quantity", (int) $producto["cantidad"], PDO::PARAM_INT);
				$stmtItem->bindValue(":unit_price_source", (string) $producto["precio_unitario"], PDO::PARAM_STR);
				$stmtItem->bindValue(":subtotal_source", (string) $producto["subtotal"], PDO::PARAM_STR);
				$stmtItem->bindValue(":tax_rate", (string) $producto["impuesto_porcentaje"], PDO::PARAM_STR);
				$stmtItem->bindValue(":tax_amount_source", (string) $producto["impuesto"], PDO::PARAM_STR);

				if($producto["regla_fiscal_id"] === null){
					$stmtItem->bindValue(":tax_rule_id", null, PDO::PARAM_NULL);
				}else{
					$stmtItem->bindValue(":tax_rule_id", (int) $producto["regla_fiscal_id"], PDO::PARAM_INT);
				}

				$stmtItem->execute();
			}

			$conexion->commit();

			return $orderId;

		}catch(Throwable $e){

			if($conexion->inTransaction()){
				$conexion->rollBack();
			}

			throw $e;
		}
	}

	/*=============================================
	ACTUALIZAR ESTADO DEL PEDIDO
	=============================================*/

	static public function mdlActualizarOrderProveedor($orderId, $status, $providerOrderId = null, $checkoutUrl = null, $errorMessage = null){

		$stmt = Conexion::conectar()->prepare(
			"UPDATE orders
			 SET status = :status,
			     provider_order_id = :provider_order_id,
			     checkout_url = :checkout_url,
			     error_message = :error_message
			 WHERE id = :id"
		);

		$stmt->bindValue(":status", $status, PDO::PARAM_STR);
		$stmt->bindValue(":provider_order_id", $providerOrderId, $providerOrderId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
		$stmt->bindValue(":checkout_url", $checkoutUrl, $checkoutUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
		$stmt->bindValue(":error_message", $errorMessage, $errorMessage === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
		$stmt->bindValue(":id", (int) $orderId, PDO::PARAM_INT);

		$stmt->execute();

		return $stmt->rowCount() >= 0;
	}

	/*=============================================
	NUEVAS COMPRAS
	=============================================*/

	static public function mdlNuevasCompras($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("INSERT INTO $tabla (id_usuario, id_producto, envio, metodo, email, direccion, pais, cantidad, detalle, pago) VALUES (:id_usuario, :id_producto, :envio, :metodo, :email, :direccion, :pais, :cantidad, :detalle, :pago)");

		$envio = isset($datos["envio"]) ? (int) $datos["envio"] : 0;

		$stmt->bindParam(":id_usuario", $datos["idUsuario"], PDO::PARAM_INT);
		$stmt->bindParam(":id_producto", $datos["idProducto"], PDO::PARAM_INT);
		$stmt->bindParam(":envio", $envio, PDO::PARAM_INT);
		$stmt->bindParam(":metodo", $datos["metodo"], PDO::PARAM_STR);
		$stmt->bindParam(":email", $datos["email"], PDO::PARAM_STR);
		$stmt->bindParam(":direccion", $datos["direccion"], PDO::PARAM_STR);
		$stmt->bindParam(":pais", $datos["pais"], PDO::PARAM_STR);
		$stmt->bindParam(":cantidad", $datos["cantidad"], PDO::PARAM_STR);
		$stmt->bindParam(":detalle", $datos["detalle"], PDO::PARAM_STR);
		$stmt->bindParam(":pago", $datos["pago"], PDO::PARAM_STR);

		if($stmt->execute()){ 

			return "ok"; 

		}else{ 

			return "error"; 

		}

		$stmt->close();

		$tmt =null;
	}

}