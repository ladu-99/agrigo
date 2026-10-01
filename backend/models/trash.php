<?php
/**
 * AgriConnect - System-wide Trash helper
 *
 * Snapshotting deletes: instead of permanently removing a record, callers can
 * capture it in the `trash` table first (a JSON snapshot of the row). Admins
 * can then Restore it (re-insert the snapshot) or Purge it (permanent delete).
 *
 * Usage in a DELETE handler, before the real delete:
 *   require_once __DIR__ . '/../../models/trash.php';
 *   trash_store($db, 'market_listings', (int)$input['id'], 'Market Listing');
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Capture a row into the trash bin. Returns true if a snapshot was stored.
 *
 * @param PDO    $db
 * @param string $sourceTable  Real table name (e.g. "market_listings")
 * @param int    $id           Primary key id of the row being deleted
 * @param string|null $label   Friendly entity name for the UI (e.g. "Market Listing")
 * @param int|null $deletedBy  users.id; defaults to the current session user
 */
function trash_store(PDO $db, $sourceTable, $id, $label = null, $deletedBy = null) {
    $id = (int)$id;
    if ($id <= 0) {
        return false;
    }

    $stmt = $db->prepare("SELECT * FROM `$sourceTable` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false; // nothing to snapshot
    }

    if ($deletedBy === null && isset($_SESSION['user_id'])) {
        $deletedBy = (int)$_SESSION['user_id'];
    }

    $label = $label ?: $sourceTable;
    $ins = $db->prepare("
        INSERT INTO trash (entity_type, source_table, entity_id, label, snapshot, deleted_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$label, $sourceTable, $id, $label, json_encode($row), $deletedBy]);
    return true;
}

/**
 * Restore a trashed record by re-inserting its snapshot into the source table.
 * Tries to keep the original id; if that collides, inserts with a fresh id.
 *
 * @return array ['ok' => bool, 'error' => string|null, 'restored_id' => int|null]
 */
function trash_restore(PDO $db, $trashId) {
    $stmt = $db->prepare("SELECT * FROM trash WHERE id = ? AND restored_at IS NULL");
    $stmt->execute([(int)$trashId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$t) {
        return ['ok' => false, 'error' => 'Trash item not found or already restored.', 'restored_id' => null];
    }

    $data = json_decode($t['snapshot'], true);
    if (!is_array($data) || empty($data)) {
        return ['ok' => false, 'error' => 'Corrupt snapshot.', 'restored_id' => null];
    }

    $table = $t['source_table'];
    $origId = $data['id'] ?? null;
    unset($data['id']);
    $cols = array_keys($data);
    if (empty($cols)) {
        return ['ok' => false, 'error' => 'Empty snapshot.', 'restored_id' => null];
    }

    // Attempt 1: preserve the original id.
    if ($origId !== null) {
        $colsAll = array_merge(['id'], $cols);
        $colList = '`' . implode('`,`', $colsAll) . '`';
        $placeholders = implode(',', array_fill(0, count($colsAll), '?'));
        $values = array_merge([$origId], array_values($data));
        try {
            $ins = $db->prepare("INSERT INTO `$table` ($colList) VALUES ($placeholders)");
            $ins->execute($values);
            $db->prepare("UPDATE trash SET restored_at = NOW() WHERE id = ?")->execute([(int)$trashId]);
            return ['ok' => true, 'error' => null, 'restored_id' => (int)$origId];
        } catch (Exception $e) {
            // fall through and insert with a new id
        }
    }

    // Attempt 2: let the DB assign a new id.
    $colList = '`' . implode('`,`', $cols) . '`';
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $ins = $db->prepare("INSERT INTO `$table` ($colList) VALUES ($placeholders)");
    $ins->execute(array_values($data));
    $newId = (int)$db->lastInsertId();
    $db->prepare("UPDATE trash SET restored_at = NOW() WHERE id = ?")->execute([(int)$trashId]);

    return ['ok' => true, 'error' => null, 'restored_id' => $newId];
}
