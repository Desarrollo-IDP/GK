<style>
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    th {
        background-color: #003366; /* Azul corporativo oscuro */
        color: #ffffff;
        font-weight: bold;
        text-align: center;
        border: 0.5px solid #002244;
        padding: 5px 3px;
        font-size: 7.5pt;
    }
    td {
        border: 0.5px solid #d0d7de;
        padding: 5px 3px;
        font-size: 7pt;
        color: #333333;
        font-weight: normal;
    }
    tr:nth-child(even) {
        background-color: #f8fafc; /* Alternancia de color suave */
    }
</style>

<table>
    <thead>
        <tr>
            <th width="12%">NOMBRE EQUIPO</th>
            <th width="8%">RESP</th>
            <th width="7%">S.O.</th>
            <th width="9%">MAC</th>
            <th width="7%">TIPO</th>
            <th width="7%">FCA</th>
            <th width="8%">MODELO</th>
            <th width="8%">N/S</th>
            <th width="6%">RAM</th>
            <th width="8%">DISCO DURO</th>
            <th width="9%">PROCESADOR</th>
            <th width="11%">OBS</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($equipos as $equipo): ?>
        <tr>
            <td width="12%"><?php echo htmlspecialchars($equipo['nombre_equipo']); ?></td>
            <td width="8%"><?php echo htmlspecialchars($equipo['responsable']); ?></td>
            <td width="7%"><?php echo htmlspecialchars($equipo['sistema_operativo']); ?></td>
            <td width="9%"><?php echo htmlspecialchars($equipo['direccion_mac']); ?></td>
            <td width="7%"><?php echo htmlspecialchars($equipo['tipo']); ?></td>
            <td width="7%"><?php echo htmlspecialchars($equipo['fabricante']); ?></td>
            <td width="8%"><?php echo htmlspecialchars($equipo['modelo']); ?></td>
            <td width="8%"><?php echo htmlspecialchars($equipo['numero_serie']); ?></td>
            <td width="6%"><?php echo htmlspecialchars($equipo['ram']); ?></td>
            <td width="8%"><?php echo htmlspecialchars($equipo['disco_duro']); ?></td>
            <td width="9%"><?php echo htmlspecialchars($equipo['procesador']); ?></td>
            <td width="11%"><?php echo htmlspecialchars($equipo['observaciones']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>