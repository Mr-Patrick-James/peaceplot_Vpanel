<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert') {
    $burialData = json_decode($_POST['burial_data'], true);
    $results = [];
    
    foreach ($burialData as $index => $burial) {
        try {
            // Validate required fields
            if (empty($burial['full_name'])) {
                $results[] = [
                    'index' => $index + 1,
                    'success' => false,
                    'message' => 'Full name is required'
                ];
                continue;
            }
            
            // Insert burial record (no lot assignment)
            $stmt = $conn->prepare("
                INSERT INTO deceased_records 
                (full_name, date_of_birth, date_of_death, date_of_burial, age, 
                 cause_of_death, next_of_kin, next_of_kin_contact, remarks) 
                VALUES 
                (:full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
                 :cause_of_death, :next_of_kin, :next_of_kin_contact, :remarks)
            ");
            
            $stmt->bindParam(':full_name', $burial['full_name']);
            $stmt->bindParam(':date_of_birth', $burial['date_of_birth']);
            $stmt->bindParam(':date_of_death', $burial['date_of_death']);
            $stmt->bindParam(':date_of_burial', $burial['date_of_burial']);
            $stmt->bindParam(':age', $burial['age']);
            $stmt->bindParam(':cause_of_death', $burial['cause_of_death']);
            $stmt->bindParam(':next_of_kin', $burial['next_of_kin']);
            $stmt->bindParam(':next_of_kin_contact', $burial['next_of_kin_contact']);
            $stmt->bindParam(':remarks', $burial['remarks']);
            
            if ($stmt->execute()) {
                $burialId = $conn->lastInsertId();
                $results[] = [
                    'index' => $index + 1,
                    'success' => true,
                    'message' => "✅ {$burial['full_name']} added (ID: {$burialId})",
                    'id' => $burialId
                ];
            } else {
                $results[] = [
                    'index' => $index + 1,
                    'success' => false,
                    'message' => 'Failed to insert record'
                ];
            }
        } catch (Exception $e) {
            $results[] = [
                'index' => $index + 1,
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Burial Records Insert - PeacePlot</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #2d2d30;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        h1 {
            color: #569cd6;
            margin-bottom: 20px;
        }
        .editor-section {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .code-editor {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 15px;
            min-height: 400px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #e6edf3;
            white-space: pre-wrap;
            overflow-y: auto;
            resize: vertical;
        }
        .btn {
            background: #238636;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #2ea043;
        }
        .btn-danger {
            background: #da3633;
        }
        .btn-danger:hover {
            background: #f85149;
        }
        .results-section {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            display: none;
        }
        .result-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            border-left: 4px solid #3e3e42;
        }
        .result-item.success {
            border-left-color: #238636;
            background: rgba(35, 134, 54, 0.1);
        }
        .result-item.error {
            border-left-color: #da3633;
            background: rgba(218, 54, 51, 0.1);
        }
        .help-section {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .help-section h3 {
            color: #569cd6;
            margin-top: 0;
        }
        .example-code {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #e6edf3;
        }
        .syntax {
            color: #569cd6;
        }
        .syntax-string {
            color: #ce9178;
        }
        .syntax-number {
            color: #b5cea8;
        }
        .syntax-comment {
            color: #6a9955;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🪦 Simple Burial Records Insert</h1>
        
        <div class="help-section">
            <h3>📋 How to Use:</h3>
            <ol>
                <li>Edit the burial records data in the code editor below</li>
                <li>Only <strong>full_name</strong> is required, other fields are optional</li>
                <li>Click "Insert Records" to add all records to database</li>
                <li>You can assign lots later using the cemetery map or lot management</li>
            </ol>
            
            <h3>📝 Data Format:</h3>
            <div class="example-code">
<span class="syntax-variable">$burials</span> = [
    [
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Juan Dela Cruz'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">75</span>,
        <span class="syntax-string">'date_of_birth'</span> => <span class="syntax-string">'1948-03-15'</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2023-12-01'</span>,
        <span class="syntax-string">'date_of_burial'</span> => <span class="syntax-string">'2023-12-03'</span>,
        <span class="syntax-string">'cause_of_death'</span> => <span class="syntax-string">'Natural Causes'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Maria Dela Cruz'</span>,
        <span class="syntax-string">'next_of_kin_contact'</span> => <span class="syntax-string">'09123456789'</span>,
        <span class="syntax-string">'remarks'</span> => <span class="syntax-string">'Peaceful passing'</span>
    ],
    [
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Maria Santos'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">82</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2023-11-15'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Jose Santos'</span>
    ]
];</div>
        </div>

        <div class="editor-section">
            <h3>✏️ Edit Burial Records Data:</h3>
            <div class="code-editor" id="codeEditor" contenteditable="true">$burials = [
    [
        'full_name' => 'Juan Dela Cruz',
        'age' => 75,
        'date_of_birth' => '1948-03-15',
        'date_of_death' => '2023-12-01',
        'date_of_burial' => '2023-12-03',
        'cause_of_death' => 'Natural Causes',
        'next_of_kin' => 'Maria Dela Cruz',
        'next_of_kin_contact' => '09123456789',
        'remarks' => 'Peaceful passing at home'
    ],
    [
        'full_name' => 'Maria Santos',
        'age' => 82,
        'date_of_death' => '2023-11-15',
        'date_of_burial' => '2023-11-17',
        'cause_of_death' => 'Cardiac Arrest',
        'next_of_kin' => 'Jose Santos',
        'next_of_kin_contact' => '09876543210',
        'remarks' => 'Passed away in hospital'
    ],
    [
        'full_name' => 'Antonio Reyes',
        'age' => 68,
        'date_of_birth' => '1955-11-08',
        'date_of_death' => '2024-01-10',
        'date_of_burial' => '2024-01-12',
        'cause_of_death' => 'Stroke',
        'next_of_kin' => 'Luz Reyes',
        'next_of_kin_contact' => '09123456789',
        'remarks' => 'Second burial in this lot'
    ]
];</div>
            
            <div style="margin-top: 15px;">
                <button class="btn" onclick="insertRecords()">📥 Insert Records</button>
                <button class="btn btn-danger" onclick="clearEditor()">🗑️ Clear Editor</button>
            </div>
        </div>

        <div class="results-section" id="resultsSection">
            <h3>📊 Insert Results:</h3>
            <div id="resultsContainer"></div>
        </div>

        <div class="help-section">
            <h3>📋 Available Fields:</h3>
            <ul>
                <li><strong>full_name</strong> (required) - Full name of deceased</li>
                <li><strong>age</strong> (optional) - Age at death</li>
                <li><strong>date_of_birth</strong> (optional) - Date of birth (YYYY-MM-DD)</li>
                <li><strong>date_of_death</strong> (optional) - Date of death (YYYY-MM-DD)</li>
                <li><strong>date_of_burial</strong> (optional) - Date of burial (YYYY-MM-DD)</li>
                <li><strong>cause_of_death</strong> (optional) - Cause of death</li>
                <li><strong>next_of_kin</strong> (optional) - Next of kin name</li>
                <li><strong>next_of_kin_contact</strong> (optional) - Contact information</li>
                <li><strong>remarks</strong> (optional) - Additional notes</li>
            </ul>
            
            <h3>🎯 Next Steps:</h3>
            <ol>
                <li>Insert burial records using this tool</li>
                <li>Go to Cemetery Lot Management to assign lots</li>
                <li>Or use Cemetery Map to assign lots and layers</li>
                <li>Records will show as "Unassigned" until you assign lots</li>
            </ol>
        </div>
    </div>

    <script>
        function insertRecords() {
            const editor = document.getElementById('codeEditor');
            const resultsSection = document.getElementById('resultsSection');
            const resultsContainer = document.getElementById('resultsContainer');
            
            try {
                // Parse the PHP array from the editor
                const code = editor.innerText;
                
                // Extract the array content (remove variable assignment)
                const arrayMatch = code.match(/\$burials\s*=\s*\[(.*)\];?\s*$/s);
                if (!arrayMatch) {
                    throw new Error('Invalid format. Please use the proper PHP array format.');
                }
                
                const arrayContent = arrayMatch[1];
                
                // Send to server for processing
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=insert&burial_data=' + encodeURIComponent(JSON.stringify([arrayContent]))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultsSection.style.display = 'block';
                        resultsContainer.innerHTML = data.results.map(result => `
                            <div class="result-item ${result.success ? 'success' : 'error'}">
                                <strong>Record ${result.index}:</strong> ${result.message}
                            </div>
                        `).join('');
                        
                        // Scroll to results
                        resultsSection.scrollIntoView({ behavior: 'smooth' });
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
                
            } catch (error) {
                alert('Error parsing data: ' + error.message);
            }
        }
        
        function clearEditor() {
            if (confirm('Clear all data from the editor?')) {
                document.getElementById('codeEditor').innerText = '$burials = [\n    \n];';
            }
        }
        
        // Auto-save to localStorage
        const editor = document.getElementById('codeEditor');
        editor.addEventListener('input', () => {
            localStorage.setItem('burialEditorContent', editor.innerText);
        });
        
        // Load from localStorage on page load
        window.addEventListener('load', () => {
            const savedContent = localStorage.getItem('burialEditorContent');
            if (savedContent) {
                editor.innerText = savedContent;
            }
        });
    </script>
</body>
</html>
