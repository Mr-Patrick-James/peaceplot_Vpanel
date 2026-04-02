<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Ensure archive_reason column exists
try {
    $stmt = $conn->prepare("PRAGMA table_info(deceased_records)");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_exists = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'archive_reason') {
            $column_exists = true;
            break;
        }
    }
    if (!$column_exists) {
        $conn->exec("ALTER TABLE deceased_records ADD COLUMN archive_reason TEXT");
    }
} catch (PDOException $e) {
    // Ignore errors here
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn, $input);
        break;
    case 'PUT':
        handlePut($conn, $input);
        break;
    case 'DELETE':
        handleDelete($conn, $input);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

function handleGet($conn) {
    try {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $showArchived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : null;
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $filterSection = isset($_GET['section']) ? trim($_GET['section']) : '';
        $filterBlock = isset($_GET['block']) ? trim($_GET['block']) : '';
        $filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
        $filterAssignment = isset($_GET['assignment']) ? trim($_GET['assignment']) : '';
        $sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'created_at';
        $sortOrder = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
        $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
        $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
        $ageMin = isset($_GET['age_min']) && $_GET['age_min'] !== '' ? intval($_GET['age_min']) : null;
        $ageMax = isset($_GET['age_max']) && $_GET['age_max'] !== '' ? intval($_GET['age_max']) : null;
        
        // Check if burial_record_images table exists
        $tableCheck = $conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='burial_record_images'");
        $tableCheck->execute();
        $imagesTableExists = $tableCheck->fetch() !== false;
        
        if ($id) {
            $stmt = $conn->prepare("
                SELECT dr.*, cl.lot_number, s.name as section, b.name as block 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                LEFT JOIN sections s ON cl.section_id = s.id
                LEFT JOIN blocks b ON s.block_id = b.id
                WHERE dr.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                // Only fetch images if table exists
                if ($imagesTableExists) {
                    try {
                        $imageStmt = $conn->prepare("
                            SELECT * FROM burial_record_images 
                            WHERE burial_record_id = :burial_record_id 
                            ORDER BY display_order ASC, created_at ASC
                        ");
                        $imageStmt->bindParam(':burial_record_id', $id);
                        $imageStmt->execute();
                        $images = $imageStmt->fetchAll();
                        $result['images'] = $images;
                    } catch (PDOException $e) {
                        // If images query fails, set empty array
                        $result['images'] = [];
                    }
                } else {
                    $result['images'] = [];
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
        } else {
            // Build query with filters
            $whereClause = "WHERE dr.is_archived = :is_archived";
            $params = [':is_archived' => $showArchived];
            
            if ($search) {
                $whereClause .= " AND (dr.full_name LIKE :search OR cl.lot_number LIKE :search OR s.name LIKE :search OR b.name LIKE :search OR dr.deceased_info LIKE :search OR dr.remarks LIKE :search)";
                $params[':search'] = "%$search%";
            }

            if ($filterSection) {
                $sectionArray = explode(',', $filterSection);
                $placeholders = [];
                foreach ($sectionArray as $i => $s) {
                    $placeholder = ":section_$i";
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = trim($s);
                }
                $whereClause .= " AND s.name IN (" . implode(',', $placeholders) . ")";
            }

            if ($filterBlock) {
                $blockArray = explode(',', $filterBlock);
                $placeholders = [];
                foreach ($blockArray as $i => $b) {
                    $placeholder = ":block_$i";
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = trim($b);
                }
                $whereClause .= " AND b.name IN (" . implode(',', $placeholders) . ")";
            }

            if ($filterStatus) {
                $statusArray = explode(',', $filterStatus);
                $placeholders = [];
                foreach ($statusArray as $i => $s) {
                    $placeholder = ":status_$i";
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = trim($s);
                }
                $whereClause .= " AND cl.status IN (" . implode(',', $placeholders) . ")";
            }

            if ($filterAssignment) {
                $assignmentArray = explode(',', $filterAssignment);
                $assignmentClauses = [];
                foreach ($assignmentArray as $assignment) {
                    $assignment = trim($assignment);
                    if ($assignment === 'Assigned') {
                        $assignmentClauses[] = "dr.lot_id IS NOT NULL";
                    } elseif ($assignment === 'Unassigned') {
                        $assignmentClauses[] = "dr.lot_id IS NULL";
                    }
                }
                if (!empty($assignmentClauses)) {
                    $whereClause .= " AND (" . implode(' OR ', $assignmentClauses) . ")";
                }
            }

            if ($startDate) {
                $whereClause .= " AND dr.date_of_death >= :start_date";
                $params[':start_date'] = $startDate;
            }

            if ($endDate) {
                $whereClause .= " AND dr.date_of_death <= :end_date";
                $params[':end_date'] = $endDate;
            }

            if ($ageMin !== null) {
                $whereClause .= " AND dr.age >= :age_min";
                $params[':age_min'] = $ageMin;
            }

            if ($ageMax !== null) {
                $whereClause .= " AND dr.age <= :age_max";
                $params[':age_max'] = $ageMax;
            }
            
            // Get total count for pagination
            $countStmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                LEFT JOIN sections s ON cl.section_id = s.id
                LEFT JOIN blocks b ON s.block_id = b.id
                $whereClause
            ");
            foreach ($params as $key => $val) {
                $countStmt->bindValue($key, $val);
            }
            $countStmt->execute();
            $totalRecords = intval($countStmt->fetchColumn());
            
            // Validate sort_by column to prevent SQL injection
            $allowedSortColumns = [
                'full_name' => 'dr.full_name',
                'date_of_death' => 'dr.date_of_death',
                'date_of_birth' => 'dr.date_of_birth',
                'date_of_burial' => 'dr.date_of_burial',
                'age' => 'dr.age',
                'created_at' => 'dr.created_at',
                'lot_number' => 'cl.lot_number',
                'section' => 's.name',
                'block' => 'b.name'
            ];
            
            $sortColumn = $allowedSortColumns[$sortBy] ?? 'dr.created_at';
            
            $sql = "
                SELECT dr.*, cl.lot_number, s.name as section, b.name as block, cl.status as lot_status
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                LEFT JOIN sections s ON cl.section_id = s.id
                LEFT JOIN blocks b ON s.block_id = b.id
                $whereClause
                ORDER BY $sortColumn $sortOrder, dr.id DESC
            ";
            
            if ($page !== null) {
                $offset = ($page - 1) * $limit;
                $sql .= " LIMIT :limit OFFSET :offset";
            }
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            
            if ($page !== null) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            // Get images for each burial record only if table exists
            if ($imagesTableExists) {
                foreach ($results as &$record) {
                    try {
                        $imageStmt = $conn->prepare("
                            SELECT * FROM burial_record_images 
                            WHERE burial_record_id = :burial_record_id 
                            ORDER BY display_order ASC, created_at ASC
                        ");
                        $imageStmt->bindParam(':burial_record_id', $record['id']);
                        $imageStmt->execute();
                        $images = $imageStmt->fetchAll();
                        $record['images'] = $images;
                    } catch (PDOException $e) {
                        // If images query fails, set empty array
                        $record['images'] = [];
                    }
                }
            } else {
                // Set empty images array for all records if table doesn't exist
                foreach ($results as &$record) {
                    $record['images'] = [];
                }
            }
            
            $response = [
                'success' => true, 
                'data' => $results,
                'pagination' => null
            ];
            
            if ($page !== null) {
                $response['pagination'] = [
                    'total_records' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $limit),
                    'current_page' => $page,
                    'limit' => $limit
                ];
            }
            
            echo json_encode($response);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePost($conn, $input) {
    try {
        if (!isset($input['full_name'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }

        $lotIdRaw = $input['lot_id'] ?? null;
        $lotId = ($lotIdRaw === '' || $lotIdRaw === null) ? null : intval($lotIdRaw);

        if ($lotId === null) {
            $stmt = $conn->prepare("
                INSERT INTO deceased_records 
                (lot_id, layer, full_name, date_of_birth, date_of_death, date_of_burial, age, 
                 cause_of_death, next_of_kin, next_of_kin_contact, deceased_info, remarks) 
                VALUES 
                (NULL, NULL, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
                 :cause_of_death, :next_of_kin, :next_of_kin_contact, :deceased_info, :remarks)
            ");

            $stmt->bindValue(':full_name', $input['full_name']);
            $stmt->bindValue(':date_of_birth', $input['date_of_birth'] ?? null);
            $stmt->bindValue(':date_of_death', $input['date_of_death'] ?? null);
            $stmt->bindValue(':date_of_burial', $input['date_of_burial'] ?? null);
            $stmt->bindValue(':age', $input['age'] ?? null);
            $stmt->bindValue(':cause_of_death', $input['cause_of_death'] ?? null);
            $stmt->bindValue(':next_of_kin', $input['next_of_kin'] ?? null);
            $stmt->bindValue(':next_of_kin_contact', $input['next_of_kin_contact'] ?? null);
            $stmt->bindValue(':deceased_info', $input['deceased_info'] ?? null);
            $stmt->bindValue(':remarks', $input['remarks'] ?? null);

            if ($stmt->execute()) {
                $lastId = $conn->lastInsertId();
                logActivity($conn, 'ADD_RECORD', 'deceased_records', $lastId, "New burial record for " . $input['full_name'] . " is added (lot unassigned)");
                echo json_encode(['success' => true, 'message' => 'Record created successfully', 'id' => $lastId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create record']);
            }
            return;
        }

        if (!isset($input['layer']) || $input['layer'] === '' || $input['layer'] === null) {
            echo json_encode(['success' => false, 'message' => 'Missing burial layer']);
            return;
        }

        $layer = intval($input['layer']);

        $stmt = $conn->prepare("SELECT is_occupied, burial_record_id FROM lot_layers WHERE lot_id = :lot_id AND layer_number = :layer");
        $stmt->bindParam(':lot_id', $lotId);
        $stmt->bindParam(':layer', $layer);
        $stmt->execute();
        $lotLayer = $stmt->fetch();

        if (!$lotLayer) {
            echo json_encode(['success' => false, 'message' => 'Layer ' . $layer . ' does not exist for this lot']);
            return;
        }

        // Allow multiple burials if it's an ash burial (based on remarks/notes containing 'ash' or user preference)
        // For now, we'll just allow it regardless of content to support the "same layer" request.
        // We removed the strict 'is_occupied' check here.

        $stmt = $conn->prepare("
            INSERT INTO deceased_records 
            (lot_id, layer, full_name, date_of_birth, date_of_death, date_of_burial, age, 
             cause_of_death, next_of_kin, next_of_kin_contact, deceased_info, remarks) 
            VALUES 
            (:lot_id, :layer, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
             :cause_of_death, :next_of_kin, :next_of_kin_contact, :deceased_info, :remarks)
        ");

        $stmt->bindValue(':lot_id', $lotId);
        $stmt->bindValue(':layer', $layer);
        $stmt->bindValue(':full_name', $input['full_name']);
        $stmt->bindValue(':date_of_birth', $input['date_of_birth'] ?? null);
        $stmt->bindValue(':date_of_death', $input['date_of_death'] ?? null);
        $stmt->bindValue(':date_of_burial', $input['date_of_burial'] ?? null);
        $stmt->bindValue(':age', $input['age'] ?? null);
        $stmt->bindValue(':cause_of_death', $input['cause_of_death'] ?? null);
        $stmt->bindValue(':next_of_kin', $input['next_of_kin'] ?? null);
        $stmt->bindValue(':next_of_kin_contact', $input['next_of_kin_contact'] ?? null);
        $stmt->bindValue(':deceased_info', $input['deceased_info'] ?? null);
        $stmt->bindValue(':remarks', $input['remarks'] ?? null);

        if ($stmt->execute()) {
            $lastId = $conn->lastInsertId();

            $updateStmt = $conn->prepare("
                UPDATE lot_layers 
                SET is_occupied = 1, burial_record_id = :burial_record_id 
                WHERE lot_id = :lot_id AND layer_number = :layer
            ");
            $updateStmt->bindParam(':burial_record_id', $lastId);
            $updateStmt->bindParam(':lot_id', $lotId);
            $updateStmt->bindParam(':layer', $layer);
            $updateStmt->execute();

            updateLotStatus($conn, $lotId);

            // Get lot number for description
            $lotStmt = $conn->prepare("SELECT lot_number FROM cemetery_lots WHERE id = :id");
            $lotStmt->bindParam(':id', $lotId);
            $lotStmt->execute();
            $lotNum = $lotStmt->fetchColumn() ?: 'Unknown';

            logActivity($conn, 'ADD_RECORD', 'deceased_records', $lastId, $input['full_name'] . " assigned on $lotNum ($lotNum occupied)");

            echo json_encode(['success' => true, 'message' => 'Record created successfully', 'id' => $lastId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create record']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePut($conn, $input) {
    try {
        if (!isset($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing record ID']);
            return;
        }

        $recordStmt = $conn->prepare("SELECT lot_id, layer FROM deceased_records WHERE id = :id");
        $recordStmt->bindParam(':id', $input['id']);
        $recordStmt->execute();
        $existing = $recordStmt->fetch();

        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            return;
        }

        $oldLotId = $existing['lot_id'] !== null ? intval($existing['lot_id']) : null;
        $oldLayer = $existing['layer'] !== null ? intval($existing['layer']) : null;

        $lotIdRaw = $input['lot_id'] ?? null;
        $newLotId = ($lotIdRaw === '' || $lotIdRaw === null) ? null : intval($lotIdRaw);
        $moveNotes = $input['move_notes'] ?? '';

        if ($newLotId === null) {
            $stmt = $conn->prepare("
                UPDATE deceased_records 
                SET lot_id = NULL,
                    layer = NULL,
                    full_name = :full_name,
                    date_of_birth = :date_of_birth,
                    date_of_death = :date_of_death,
                    date_of_burial = :date_of_burial,
                    age = :age,
                    cause_of_death = :cause_of_death,
                    next_of_kin = :next_of_kin,
                    next_of_kin_contact = :next_of_kin_contact,
                    deceased_info = :deceased_info,
                    remarks = :remarks,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->bindValue(':id', $input['id']);
            $stmt->bindValue(':full_name', $input['full_name'] ?? null);
            $stmt->bindValue(':date_of_birth', $input['date_of_birth'] ?? null);
            $stmt->bindValue(':date_of_death', $input['date_of_death'] ?? null);
            $stmt->bindValue(':date_of_burial', $input['date_of_burial'] ?? null);
            $stmt->bindValue(':age', $input['age'] ?? null);
            $stmt->bindValue(':cause_of_death', $input['cause_of_death'] ?? null);
            $stmt->bindValue(':next_of_kin', $input['next_of_kin'] ?? null);
            $stmt->bindValue(':next_of_kin_contact', $input['next_of_kin_contact'] ?? null);
            $stmt->bindValue(':deceased_info', $input['deceased_info'] ?? null);
            $stmt->bindValue(':remarks', $input['remarks'] ?? null);

            if ($stmt->execute()) {
                if ($oldLotId !== null && $oldLayer !== null) {
                    // Get old lot number for description
                    $oldLotStmt = $conn->prepare("SELECT lot_number FROM cemetery_lots WHERE id = :id");
                    $oldLotStmt->bindParam(':id', $oldLotId);
                    $oldLotStmt->execute();
                    $oldLotNum = $oldLotStmt->fetchColumn() ?: 'Unknown';

                    $updateStmt = $conn->prepare("
                        UPDATE lot_layers 
                        SET is_occupied = 0, burial_record_id = NULL 
                        WHERE lot_id = :lot_id AND layer_number = :layer AND burial_record_id = :burial_record_id
                    ");
                    $updateStmt->bindParam(':lot_id', $oldLotId);
                    $updateStmt->bindParam(':layer', $oldLayer);
                    $updateStmt->bindParam(':burial_record_id', $input['id']);
                    $updateStmt->execute();
                    updateLotStatus($conn, $oldLotId);

                    $logMsg = ($input['full_name'] ?? 'Record') . " is unassigned from lot $oldLotNum";
                    if ($moveNotes) {
                        $logMsg .= " (Reason: $moveNotes)";
                    }
                    logActivity($conn, 'UPDATE_RECORD', 'deceased_records', $input['id'], $logMsg);
                } else {
                    logActivity($conn, 'UPDATE_RECORD', 'deceased_records', $input['id'], ($input['full_name'] ?? 'Record') . " is updated (lot unassigned)");
                }

                echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update record']);
            }
            return;
        }

        if (!isset($input['layer']) || $input['layer'] === '' || $input['layer'] === null) {
            echo json_encode(['success' => false, 'message' => 'Missing burial layer']);
            return;
        }

        $newLayer = intval($input['layer']);

        $ensureLayerStmt = $conn->prepare("
            INSERT OR IGNORE INTO lot_layers (lot_id, layer_number, is_occupied) 
            VALUES (:lot_id, :layer_number, 0)
        ");
        $ensureLayerStmt->bindParam(':lot_id', $newLotId);
        $ensureLayerStmt->bindParam(':layer_number', $newLayer);
        $ensureLayerStmt->execute();

        $layerCheckStmt = $conn->prepare("SELECT is_occupied, burial_record_id FROM lot_layers WHERE lot_id = :lot_id AND layer_number = :layer");
        $layerCheckStmt->bindParam(':lot_id', $newLotId);
        $layerCheckStmt->bindParam(':layer', $newLayer);
        $layerCheckStmt->execute();
        $layerRow = $layerCheckStmt->fetch();

        // Removed strict check for 'is_occupied' to allow multiple burials per layer
        
        $stmt = $conn->prepare("
            UPDATE deceased_records 
            SET lot_id = :lot_id,
                layer = :layer,
                full_name = :full_name,
                date_of_birth = :date_of_birth,
                date_of_death = :date_of_death,
                date_of_burial = :date_of_burial,
                age = :age,
                cause_of_death = :cause_of_death,
                next_of_kin = :next_of_kin,
                next_of_kin_contact = :next_of_kin_contact,
                deceased_info = :deceased_info,
                remarks = :remarks,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->bindValue(':id', $input['id']);
        $stmt->bindValue(':lot_id', $newLotId);
        $stmt->bindValue(':layer', $newLayer);
        $stmt->bindValue(':full_name', $input['full_name'] ?? null);
        $stmt->bindValue(':date_of_birth', $input['date_of_birth'] ?? null);
        $stmt->bindValue(':date_of_death', $input['date_of_death'] ?? null);
        $stmt->bindValue(':date_of_burial', $input['date_of_burial'] ?? null);
        $stmt->bindValue(':age', $input['age'] ?? null);
        $stmt->bindValue(':cause_of_death', $input['cause_of_death'] ?? null);
        $stmt->bindValue(':next_of_kin', $input['next_of_kin'] ?? null);
        $stmt->bindValue(':next_of_kin_contact', $input['next_of_kin_contact'] ?? null);
        $stmt->bindValue(':deceased_info', $input['deceased_info'] ?? null);
        $stmt->bindValue(':remarks', $input['remarks'] ?? null);

        if ($stmt->execute()) {
            if ($oldLotId !== null && $oldLayer !== null && ($oldLotId !== $newLotId || $oldLayer !== $newLayer)) {
                $freeStmt = $conn->prepare("
                    UPDATE lot_layers 
                    SET is_occupied = 0, burial_record_id = NULL 
                    WHERE lot_id = :lot_id AND layer_number = :layer AND burial_record_id = :burial_record_id
                ");
                $freeStmt->bindParam(':lot_id', $oldLotId);
                $freeStmt->bindParam(':layer', $oldLayer);
                $freeStmt->bindParam(':burial_record_id', $input['id']);
                $freeStmt->execute();
                updateLotStatus($conn, $oldLotId);
            }

            $occupyStmt = $conn->prepare("
                UPDATE lot_layers 
                SET is_occupied = 1, burial_record_id = :burial_record_id, updated_at = CURRENT_TIMESTAMP
                WHERE lot_id = :lot_id AND layer_number = :layer
            ");
            $occupyStmt->bindParam(':burial_record_id', $input['id']);
            $occupyStmt->bindParam(':lot_id', $newLotId);
            $occupyStmt->bindParam(':layer', $newLayer);
            $occupyStmt->execute();

            updateLotStatus($conn, $newLotId);

            // Get lot number for description
            $lotStmt = $conn->prepare("SELECT lot_number FROM cemetery_lots WHERE id = :id");
            $lotStmt->bindParam(':id', $newLotId);
            $lotStmt->execute();
            $lotNum = $lotStmt->fetchColumn() ?: 'Unknown';

            $name = $input['full_name'] ?? 'Record';
            if ($oldLotId !== null && ($oldLotId !== $newLotId || $oldLayer !== $newLayer)) {
                $logMsg = "$name is moved to $lotNum layer $newLayer";
                if ($moveNotes) {
                    $logMsg .= " (Reason: $moveNotes)";
                }
                logActivity($conn, 'MOVE_RECORD', 'deceased_records', $input['id'], $logMsg);
            } else {
                logActivity($conn, 'UPDATE_RECORD', 'deceased_records', $input['id'], "$name assigned on $lotNum ($lotNum occupied)");
            }

            echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update record']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDelete($conn, $input) {
    try {
        $ids = isset($input['ids']) ? $input['ids'] : (isset($input['id']) ? [$input['id']] : (isset($_GET['id']) ? [$_GET['id']] : []));
        $action = $input['action'] ?? 'archive'; // Default to archive
        $reason = $input['reason'] ?? ''; // Reason for archiving
        
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Missing record ID(s)']);
            return;
        }

        $successCount = 0;
        $errors = [];

        foreach ($ids as $id) {
            // Get the burial record info before deletion
            $stmt = $conn->prepare("
                SELECT dr.full_name, dr.lot_id, dr.layer, cl.lot_number 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                WHERE dr.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $record = $stmt->fetch();
            
            if (!$record) {
                $errors[] = "Record ID $id not found";
                continue;
            }
            
            $recordName = $record['full_name'];
            $lotInfo = $record['lot_number'] ? "lot " . $record['lot_number'] : "lot unassigned";
            
            if ($action === 'restore') {
                $stmt = $conn->prepare("UPDATE deceased_records SET is_archived = 0, archive_reason = NULL WHERE id = :id");
                $stmt->bindParam(':id', $id);
                if ($stmt->execute()) {
                    logActivity($conn, 'RESTORE_RECORD', 'deceased_records', $id, "Burial record for $recordName is restored ($lotInfo)");
                    
                    // Re-occupy the lot layer if it was previously assigned
                    if ($record && $record['lot_id'] && $record['layer']) {
                        $occStmt = $conn->prepare("
                            UPDATE lot_layers 
                            SET is_occupied = 1, burial_record_id = :burial_record_id 
                            WHERE lot_id = :lot_id AND layer_number = :layer
                        ");
                        $occStmt->bindParam(':burial_record_id', $id);
                        $occStmt->bindParam(':lot_id', $record['lot_id']);
                        $occStmt->bindParam(':layer', $record['layer']);
                        $occStmt->execute();
                        
                        updateLotStatus($conn, $record['lot_id']);
                    }
                    $successCount++;
                } else {
                    $errors[] = "Failed to restore record ID $id";
                }
                continue;
            }

            if ($action === 'permanent_delete') {
                $stmt = $conn->prepare("DELETE FROM deceased_records WHERE id = :id");
                $stmt->bindParam(':id', $id);
                if ($stmt->execute()) {
                    logActivity($conn, 'DELETE_RECORD', 'deceased_records', $id, "Burial record for $recordName is permanently removed ($lotInfo)");
                    
                    // Free the lot layer
                    if ($record && $record['lot_id'] && $record['layer']) {
                        $freeStmt = $conn->prepare("
                            UPDATE lot_layers 
                            SET is_occupied = 0, burial_record_id = NULL 
                            WHERE lot_id = :lot_id AND layer_number = :layer
                        ");
                        $freeStmt->bindParam(':lot_id', $record['lot_id']);
                        $freeStmt->bindParam(':layer', $record['layer']);
                        $freeStmt->execute();
                        
                        updateLotStatus($conn, $record['lot_id']);
                    }
                    $successCount++;
                } else {
                    $errors[] = "Failed to permanently delete record ID $id";
                }
                continue;
            }

            // Default: Archive
            $stmt = $conn->prepare("UPDATE deceased_records SET is_archived = 1, archive_reason = :reason WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':reason', $reason);
            
            if ($stmt->execute()) {
                $logMsg = "Burial record for $recordName is moved to archive ($lotInfo)";
                if ($reason) $logMsg .= " | Reason: $reason";
                
                logActivity($conn, 'ARCHIVE_RECORD', 'deceased_records', $id, $logMsg);
                // Update the lot layer to mark it as vacant
                if ($record && $record['lot_id']) {
                    $updateStmt = $conn->prepare("
                        UPDATE lot_layers 
                        SET is_occupied = 0, burial_record_id = NULL 
                        WHERE lot_id = :lot_id AND layer_number = :layer
                    ");
                    $updateStmt->bindParam(':lot_id', $record['lot_id']);
                    $updateStmt->bindParam(':layer', $record['layer']);
                    $updateStmt->execute();
                    
                    updateLotStatus($conn, $record['lot_id']);
                }
                $successCount++;
            } else {
                $errors[] = "Failed to archive record ID $id";
            }
        }
        
        if ($successCount > 0) {
            echo json_encode(['success' => true, 'message' => "$successCount record(s) processed successfully", 'errors' => $errors]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No records were processed', 'errors' => $errors]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateLotStatus($conn, $lotId) {
    // 1. Sync lot_layers with deceased_records for this lot
    // First, reset all layers for this lot to vacant
    $conn->prepare("UPDATE lot_layers SET is_occupied = 0, burial_record_id = NULL WHERE lot_id = :lot_id")
         ->execute([':lot_id' => $lotId]);
         
    // Then, mark layers that have active burial records as occupied
    // We'll use the most recent record per layer to set burial_record_id
    $conn->prepare("
        UPDATE lot_layers 
        SET is_occupied = 1, 
            burial_record_id = (
                SELECT id FROM deceased_records 
                WHERE lot_id = :lot_id AND layer = lot_layers.layer_number AND is_archived = 0 
                ORDER BY created_at DESC LIMIT 1
            )
        WHERE lot_id = :lot_id 
        AND EXISTS (
            SELECT 1 FROM deceased_records 
            WHERE lot_id = :lot_id AND layer = lot_layers.layer_number AND is_archived = 0
        )
    ")->execute([':lot_id' => $lotId]);

    // 2. Determine and update the overall lot status
    // A lot is 'Occupied' if any non-archived record is assigned to it
    $checkStmt = $conn->prepare("SELECT COUNT(*) as occupied_count FROM deceased_records WHERE lot_id = :lot_id AND is_archived = 0");
    $checkStmt->bindParam(':lot_id', $lotId);
    $checkStmt->execute();
    $result = $checkStmt->fetch();

    $status = ($result && intval($result['occupied_count']) > 0) ? 'Occupied' : 'Vacant';
    $updateStmt = $conn->prepare("UPDATE cemetery_lots SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :lot_id");
    $updateStmt->bindParam(':status', $status);
    $updateStmt->bindParam(':lot_id', $lotId);
    $updateStmt->execute();
}
?>
