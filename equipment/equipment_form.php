<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * equipment/equipment_form.php - Logica compartida de alta y edicion de equipos
 *
 * Uso:
 *   $equipment_row = array()  (nuevo)  o  fila de equipment (editar)
 *   $form_action   = 'equipment/create.php' o 'equipment/edit.php'
 *   $titulo, $submit_text  se definen antes de incluir
 *
 * Nota de laboratorio: el campo `description` se guarda tal cual, sin
 * sanear. Esa es la entrada de LAB-04; el escapado falta al imprimir, en
 * equipment/view.php.
 */

if (!defined('SIGI_APP')) {
    die('Acceso directo no permitido.');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';

$marcas      = db_all("SELECT id, nombre FROM brands WHERE estado = 'Activo' ORDER BY nombre");
$categorias  = db_all("SELECT id, nombre FROM categories WHERE estado = 'Activo' ORDER BY nombre");

$es_edicion = !empty($equipment_row);
$errores    = array();

$old = array(
    'inventory_number'  => $es_edicion ? $equipment_row['inventory_number']  : '',
    'serial_number'     => $es_edicion ? $equipment_row['serial_number']     : '',
    'brand_id'          => $es_edicion ? $equipment_row['brand_id']          : '',
    'category_id'       => $es_edicion ? $equipment_row['category_id']       : '',
    'model'             => $es_edicion ? $equipment_row['model']             : '',
    'processor'         => $es_edicion ? $equipment_row['processor']         : '',
    'ram'               => $es_edicion ? $equipment_row['ram']               : '',
    'storage'           => $es_edicion ? $equipment_row['storage']           : '',
    'operating_system'  => $es_edicion ? $equipment_row['operating_system']  : '',
    'purchase_date'     => $es_edicion ? $equipment_row['purchase_date']     : '',
    'price'             => $es_edicion ? $equipment_row['price']             : '',
    'location'          => $es_edicion ? $equipment_row['location']          : '',
    'status'            => $es_edicion ? $equipment_row['status']            : 'Disponible',
    'description'       => $es_edicion ? $equipment_row['description']       : '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['inventory_number'] = strtoupper(trim(input_post('inventory_number')));
    $old['serial_number']    = strtoupper(trim(input_post('serial_number')));
    $old['brand_id']         = input_post('brand_id');
    $old['category_id']      = input_post('category_id');
    $old['model']            = trim(input_post('model'));
    $old['processor']        = trim(input_post('processor'));
    $old['ram']              = trim(input_post('ram'));
    $old['storage']          = trim(input_post('storage'));
    $old['operating_system'] = trim(input_post('operating_system'));
    $old['purchase_date']    = trim(input_post('purchase_date'));
    $old['price']            = trim(input_post('price'));
    $old['location']         = trim(input_post('location'));
    $old['status']           = input_post('status');
    $old['description']      = input_post('description');

    // -----------------------------------------------------------------
    // Validaciones
    // -----------------------------------------------------------------
    if ($old['inventory_number'] === '') {
        $errores[] = 'El numero de inventario es obligatorio.';
    } elseif (!preg_match('/^[A-Z0-9-]{3,30}$/', $old['inventory_number'])) {
        $errores[] = 'El numero de inventario solo admite letras, numeros y guiones.';
    } else {
        $existe = db_value(
            "SELECT id FROM equipment WHERE inventory_number = ? AND id <> ?",
            array($old['inventory_number'], $es_edicion ? (int) $equipment_row['id'] : 0)
        );
        if ($existe) {
            $errores[] = 'Ese numero de inventario ya esta registrado.';
        }
    }

    if ($old['serial_number'] === '') {
        $errores[] = 'El numero de serie es obligatorio.';
    } else {
        $existe = db_value(
            "SELECT id FROM equipment WHERE serial_number = ? AND id <> ?",
            array($old['serial_number'], $es_edicion ? (int) $equipment_row['id'] : 0)
        );
        if ($existe) {
            $errores[] = 'Ese numero de serie ya esta registrado.';
        }
    }

    if ($old['model'] === '') {
        $errores[] = 'El modelo es obligatorio.';
    }
    if ($old['brand_id'] === '' || !is_numeric($old['brand_id'])) {
        $errores[] = 'Debe seleccionar una marca.';
    }
    if ($old['category_id'] === '' || !is_numeric($old['category_id'])) {
        $errores[] = 'Debe seleccionar una categoria.';
    }
    if (!in_array($old['status'], array('Disponible', 'Asignado', 'Mantenimiento', 'Baja'))) {
        $errores[] = 'El estado no es valido.';
    }
    if ($old['purchase_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['purchase_date'])) {
        $errores[] = 'La fecha de compra no tiene un formato valido.';
    }
    if ($old['price'] !== '' && !is_numeric($old['price'])) {
        $errores[] = 'El costo debe ser un numero.';
    }

    // El estado 'Asignado' lo gobierna el modulo de asignaciones: un
    // equipo no se marca asignado desde aqui, se asigna a un empleado.
    if ($old['status'] === 'Asignado') {
        $errores[] = 'El estado Asignado se aplica al registrar una asignacion, no al dar de alta el equipo.';
    }

    // -----------------------------------------------------------------
    // Persistencia
    // -----------------------------------------------------------------
    if (count($errores) === 0) {
        $params = array(
            $old['inventory_number'],
            $old['serial_number'],
            (int) $old['brand_id'],
            (int) $old['category_id'],
            $old['model'],
            $old['processor'],
            $old['ram'],
            $old['storage'],
            $old['operating_system'],
            $old['purchase_date'] === '' ? null : $old['purchase_date'],
            $old['price'] === '' ? null : (float) $old['price'],
            $old['location'],
            $old['status'],
            $old['description'],
        );

        if ($es_edicion) {
            $params[] = (int) $equipment_row['id'];
            db_exec(
                "UPDATE equipment
                    SET inventory_number = ?, serial_number = ?, brand_id = ?, category_id = ?,
                        model = ?, processor = ?, ram = ?, storage = ?, operating_system = ?,
                        purchase_date = ?, price = ?, location = ?, status = ?, description = ?
                  WHERE id = ?",
                $params
            );
            $id_equipo = (int) $equipment_row['id'];
            audit_log('UPDATE', 'equipment',
                'Actualizo el equipo ' . $old['inventory_number'] . ' (id ' . $id_equipo . ')');
            redirect('equipment/index.php?msg=updated');
        } else {
            db_exec(
                "INSERT INTO equipment
                    (inventory_number, serial_number, brand_id, category_id, model, processor,
                     ram, storage, operating_system, purchase_date, price, location, status, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                $params
            );
            $id_equipo = db_insert_id();
            audit_log('CREATE', 'equipment',
                'Registro el equipo ' . $old['inventory_number'] . ' (id ' . $id_equipo . ')');
            redirect('equipment/index.php?msg=created');
        }
    }
}
