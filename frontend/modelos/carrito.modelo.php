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
	NUEVAS COMPRAS
	=============================================*/

	static public function mdlNuevasCompras($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("INSERT INTO $tabla (id_usuario, id_producto, metodo, email, direccion, pais, cantidad, detalle, pago) VALUES (:id_usuario, :id_producto, :metodo, :email, :direccion, :pais, :cantidad, :detalle, :pago)");

		$stmt->bindParam(":id_usuario", $datos["idUsuario"], PDO::PARAM_INT);
		$stmt->bindParam(":id_producto", $datos["idProducto"], PDO::PARAM_INT);
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