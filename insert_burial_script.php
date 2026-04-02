<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Burial Records Script - PeacePlot</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            background: #2d2d30;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        h1 {
            color: #569cd6;
            margin-bottom: 20px;
        }
        .code-section {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .code-block {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            overflow-x: auto;
        }
        .code-block pre {
            margin: 0;
            color: #e6edf3;
            font-size: 14px;
            line-height: 1.5;
        }
        .status {
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
        }
        .success {
            background: #0f5132;
            color: #75b798;
            border: 1px solid #1e7e34;
        }
        .error {
            background: #58151c;
            color: #f8d7da;
            border: 1px solid #842029;
        }
        .info {
            background: #084298;
            color: #9ec5fe;
            border: 1px solid #0c5460;
        }
        .warning {
            background: #664d03;
            color: #ffc107;
            border: 1px solid #b08d00;
        }
        .example {
            background: #2d3748;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .example h4 {
            color: #a0aec0;
            margin-top: 0;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #3e3e42;
        }
        th {
            background: #252526;
            color: #569cd6;
        }
        .btn {
            background: #238636;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            margin: 5px;
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
        <h1>🪦 Burial Records Insert Script</h1>
        
        <div class="info">
            <strong>📋 Instructions:</strong> Use the PHP code below to insert burial records. Copy this code into a PHP file or run it directly.
        </div>

        <div class="code-section">
            <h3>🔧 Quick Insert Function</h3>
            <div class="code-block">
                <pre><span class="syntax-comment">// Function to insert a burial record with layer</span>
<span class="syntax-string">function</span> <span class="syntax">insertBurialRecord</span>(<span class="syntax-variable">$conn</span>, <span class="syntax-variable">$data</span>) {
    <span class="syntax-string">try</span> {
        <span class="syntax-comment">// Validate required fields</span>
        <span class="syntax-string">if</span> (!<span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>] || !<span class="syntax-variable">$data</span>[<span class="syntax-string">'layer'</span>] || !<span class="syntax-variable">$data</span>[<span class="syntax-string">'full_name'</span>]) {
            <span class="syntax-string">throw new</span> <span class="syntax">Exception</span>(<span class="syntax-string">"lot_id, layer, and full_name are required"</span>);
        }
        
        <span class="syntax-comment">// Check if layer is available</span>
        <span class="syntax-variable">$stmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
            SELECT is_occupied FROM lot_layers 
            WHERE lot_id = :lot_id AND layer_number = :layer
        "</span>);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':layer'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'layer'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">execute</span>();
        <span class="syntax-variable">$layerInfo</span> = <span class="syntax-variable">$stmt</span>-><span class="syntax">fetch</span>();
        
        <span class="syntax-string">if</span> (!<span class="syntax-variable">$layerInfo</span>) {
            <span class="syntax-string">throw new</span> <span class="syntax">Exception</span>(<span class="syntax-string">"Layer {$data['layer']} does not exist for this lot"</span>);
        }
        
        <span class="syntax-string">if</span> (<span class="syntax-variable">$layerInfo</span>[<span class="syntax-string">'is_occupied'</span>]) {
            <span class="syntax-string">throw new</span> <span class="syntax">Exception</span>(<span class="syntax-string">"Layer {$data['layer']} is already occupied"</span>);
        }
        
        <span class="syntax-comment">// Start transaction</span>
        <span class="syntax-variable">$conn</span>-><span class="syntax">beginTransaction</span>();
        
        <span class="syntax-comment">// Insert burial record</span>
        <span class="syntax-variable">$stmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
            INSERT INTO deceased_records 
            (lot_id, layer, full_name, date_of_birth, date_of_death, date_of_burial, age, 
             cause_of_death, next_of_kin, next_of_kin_contact, remarks) 
            VALUES 
            (:lot_id, :layer, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
             :cause_of_death, :next_of_kin, :next_of_kin_contact, :remarks)
        "</span>);
        
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':layer'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'layer'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':full_name'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'full_name'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':date_of_birth'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'date_of_birth'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':date_of_death'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'date_of_death'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':date_of_burial'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'date_of_burial'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':age'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'age'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':cause_of_death'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'cause_of_death'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':next_of_kin'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'next_of_kin'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':next_of_kin_contact'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'next_of_kin_contact'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':remarks'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'remarks'</span>]);
        
        <span class="syntax-string">if</span> (<span class="syntax-variable">$stmt</span>-><span class="syntax">execute</span>()) {
            <span class="syntax-variable">$burialRecordId</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">lastInsertId</span>();
            
            <span class="syntax-comment">// Update the lot layer to mark it as occupied</span>
            <span class="syntax-variable">$updateStmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
                UPDATE lot_layers 
                SET is_occupied = 1, burial_record_id = :burial_record_id 
                WHERE lot_id = :lot_id AND layer_number = :layer
            "</span>);
            <span class="syntax-variable">$updateStmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':burial_record_id'</span>, <span class="syntax-variable">$burialRecordId</span>);
            <span class="syntax-variable">$updateStmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
            <span class="syntax-variable">$updateStmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':layer'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'layer'</span>]);
            <span class="syntax-variable">$updateStmt</span>-><span class="syntax">execute</span>();
            
            <span class="syntax-comment">// Update lot status</span>
            <span class="syntax-variable">$checkStmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
                SELECT COUNT(*) as occupied_count FROM lot_layers 
                WHERE lot_id = :lot_id AND is_occupied = 1
            "</span>);
            <span class="syntax-variable">$checkStmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
            <span class="syntax-variable">$checkStmt</span>-><span class="syntax">execute</span>();
            <span class="syntax-variable">$result</span> = <span class="syntax-variable">$checkStmt</span>-><span class="syntax">fetch</span>();
            
            <span class="syntax-variable">$status</span> = <span class="syntax-variable">$result</span>[<span class="syntax-string">'occupied_count'</span>] > <span class="syntax-number">0</span> ? <span class="syntax-string">'Occupied'</span> : <span class="syntax-string">'Vacant'</span>;
            <span class="syntax-variable">$conn</span>-><span class="syntax">exec</span>(<span class="syntax-string">"UPDATE cemetery_lots SET status = '$status' WHERE id = {$data['lot_id']}"</span>);
            
            <span class="syntax-variable">$conn</span>-><span class="syntax">commit</span>();
            
            <span class="syntax-string">return</span> [
                <span class="syntax-string">'success'</span> => <span class="syntax-string">true</span>,
                <span class="syntax-string">'message'</span> => <span class="syntax-string">"Successfully added {$data['full_name']} to Layer {$data['layer']}"</span>,
                <span class="syntax-string">'id'</span> => <span class="syntax-variable">$burialRecordId</span>
            ];
        }
    } <span class="syntax-string">catch</span> (<span class="syntax">Exception</span> <span class="syntax-variable">$e</span>) {
        <span class="syntax-string">if</span> (<span class="syntax-variable">$conn</span>-><span class="syntax">inTransaction</span>()) {
            <span class="syntax-variable">$conn</span>-><span class="syntax">rollback</span>();
        }
        <span class="syntax-string">return</span> [
            <span class="syntax-string">'success'</span> => <span class="syntax-string">false</span>,
            <span class="syntax-string">'message'</span> => <span class="syntax-variable">$e</span>-><span class="syntax">getMessage</span>()
        ];
    }
}</pre>
            </div>
        </div>

        <div class="code-section">
            <h3>📝 Example Usage</h3>
            <div class="code-block">
                <pre><span class="syntax-comment">// Include database connection</span>
<span class="syntax-string">require_once</span> <span class="syntax-string">'config/database.php'</span>;

<span class="syntax-variable">$database</span> = <span class="syntax-string">new</span> <span class="syntax">Database</span>();
<span class="syntax-variable">$conn</span> = <span class="syntax-variable">$database</span>-><span class="syntax">getConnection</span>();

<span class="syntax-comment">// Example burial records to insert</span>
<span class="syntax-variable">$burials</span> = [
    [
        <span class="syntax-string">'lot_id'</span> => <span class="syntax-number">1</span>,        <span class="syntax-comment">// Replace with actual lot ID</span>
        <span class="syntax-string">'layer'</span> => <span class="syntax-number">1</span>,         <span class="syntax-comment">// Layer 1</span>
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Juan Dela Cruz'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">75</span>,
        <span class="syntax-string">'date_of_birth'</span> => <span class="syntax-string">'1948-03-15'</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2023-12-01'</span>,
        <span class="syntax-string">'date_of_burial'</span> => <span class="syntax-string">'2023-12-03'</span>,
        <span class="syntax-string">'cause_of_death'</span> => <span class="syntax-string">'Natural Causes'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Maria Dela Cruz'</span>,
        <span class="syntax-string">'next_of_kin_contact'</span> => <span class="syntax-string">'09123456789'</span>,
        <span class="syntax-string">'remarks'</span> => <span class="syntax-string">'Peaceful passing at home'</span>
    ],
    [
        <span class="syntax-string">'lot_id'</span> => <span class="syntax-number">2</span>,        <span class="syntax-comment">// Replace with actual lot ID</span>
        <span class="syntax-string">'layer'</span> => <span class="syntax-number">1</span>,         <span class="syntax-comment">// Layer 1</span>
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Maria Santos'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">82</span>,
        <span class="syntax-string">'date_of_birth'</span> => <span class="syntax-string">'1941-07-22'</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2023-11-15'</span>,
        <span class="syntax-string">'date_of_burial'</span> => <span class="syntax-string">'2023-11-17'</span>,
        <span class="syntax-string">'cause_of_death'</span> => <span class="syntax-string">'Cardiac Arrest'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Jose Santos'</span>,
        <span class="syntax-string">'next_of_kin_contact'</span> => <span class="syntax-string">'09876543210'</span>,
        <span class="syntax-string">'remarks'</span> => <span class="syntax-string">'Passed away in hospital'</span>
    ],
    [
        <span class="syntax-string">'lot_id'</span> => <span class="syntax-number">3</span>,        <span class="syntax-comment">// Replace with actual lot ID</span>
        <span class="syntax-string">'layer'</span> => <span class="syntax-number">2</span>,         <span class="syntax-comment">// Layer 2 (if lot has multiple layers)</span>
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Antonio Reyes'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">68</span>,
        <span class="syntax-string">'date_of_birth'</span> => <span class="syntax-string">'1955-11-08'</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2024-01-10'</span>,
        <span class="syntax-string">'date_of_burial'</span> => <span class="syntax-string">'2024-01-12'</span>,
        <span class="syntax-string">'cause_of_death'</span> => <span class="syntax-string">'Stroke'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Luz Reyes'</span>,
        <span class="syntax-string">'next_of_kin_contact'</span> => <span class="syntax-string">'09123456789'</span>,
        <span class="syntax-string">'remarks'</span> => <span class="syntax-string">'Second burial in this lot'</span>
    ]
];

<span class="syntax-comment">// Insert each burial record</span>
<span class="syntax-string">foreach</span> (<span class="syntax-variable">$burials</span> <span class="syntax-string">as</span> <span class="syntax-variable">$burial</span>) {
    <span class="syntax-variable">$result</span> = <span class="syntax">insertBurialRecord</span>(<span class="syntax-variable">$conn</span>, <span class="syntax-variable">$burial</span>);
    
    <span class="syntax-string">if</span> (<span class="syntax-variable">$result</span>[<span class="syntax-string">'success'</span>]) {
        <span class="syntax">echo</span> <span class="syntax-string">"✅ {$result['message']}\n"</span>;
    } <span class="syntax-string">else</span> {
        <span class="syntax">echo</span> <span class="syntax-string">"❌ Error: {$result['message']}\n"</span>;
    }
}</pre>
            </div>
        </div>

        <div class="code-section">
            <h3>🏗️ Available Lots Reference</h3>
            <?php
            if ($conn) {
                try {
                    $stmt = $conn->query("SELECT id, lot_number, section, block FROM cemetery_lots ORDER BY lot_number");
                    $lots = $stmt->fetchAll();
                    
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Lot Number</th><th>Section</th><th>Block</th></tr>';
                    foreach ($lots as $lot) {
                        echo '<tr>';
                        echo '<td>' . $lot['id'] . '</td>';
                        echo '<td>' . htmlspecialchars($lot['lot_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($lot['section']) . '</td>';
                        echo '<td>' . htmlspecialchars($lot['block'] ?? 'N/A') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } catch (Exception $e) {
                    echo '<div class="error">Error loading lots: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                echo '<div class="error">Database connection failed</div>';
            }
            ?>
        </div>

        <div class="code-section">
            <h3>⚡ Quick Batch Insert Script</h3>
            <div class="code-block">
                <pre><span class="syntax-comment">// Save this as insert_burials.php and run it</span>
<span class="syntax-string">&lt;?php</span>
<span class="syntax-string">require_once</span> <span class="syntax-string">'config/database.php'</span>;

<span class="syntax-variable">$database</span> = <span class="syntax-string">new</span> <span class="syntax">Database</span>();
<span class="syntax-variable">$conn</span> = <span class="syntax-variable">$database</span>-><span class="syntax">getConnection</span>();

<span class="syntax-comment">// Copy the insertBurialRecord function from above here</span>
<span class="syntax-comment">// ... (paste the function here)</span>

<span class="syntax-comment">// Your burial data</span>
<span class="syntax-variable">$burials</span> = [
    <span class="syntax-comment">// Add your burial records here</span>
];

<span class="syntax-comment">// Insert all records</span>
<span class="syntax-string">foreach</span> (<span class="syntax-variable">$burials</span> <span class="syntax-string">as</span> <span class="syntax-variable">$burial</span>) {
    <span class="syntax-variable">$result</span> = <span class="syntax">insertBurialRecord</span>(<span class="syntax-variable">$conn</span>, <span class="syntax-variable">$burial</span>);
    <span class="syntax">echo</span> <span class="syntax-variable">$result</span>[<span class="syntax-string">'success'</span>] ? <span class="syntax-string">"✅ {$result['message']}\n"</span> : <span class="syntax-string">"❌ {$result['message']}\n"</span>;
}

<span class="syntax">echo</span> <span class="syntax-string">"\n🎉 Batch insert completed!\n"</span>;
<span class="syntax-string">?&gt;</span></pre>
            </div>
        </div>

        <div class="warning">
            <strong>⚠️ Important Notes:</strong>
            <ul>
                <li>Replace <code>lot_id</code> with actual lot IDs from the reference table above</li>
                <li>Layer numbers start from 1 (Layer 1, Layer 2, etc.)</li>
                <li>Make sure layers exist in the lot_layers table before inserting</li>
                <li>Use proper date format: YYYY-MM-DD</li>
                <li>Only required fields: lot_id, layer, full_name</li>
            </ul>
        </div>
    </div>
</body>
</html>
