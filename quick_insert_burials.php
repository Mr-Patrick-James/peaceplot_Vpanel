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
    <title>Quick Insert Burials - PeacePlot</title>
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
        .success {
            background: #0f5132;
            color: #75b798;
            border: 1px solid #1e7e34;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
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
        <h1>🚀 Quick Insert Burials (Layer 1 Default)</h1>
        
        <div class="success">
            <strong>📋 Instructions:</strong> All burials will be automatically assigned to Layer 1. Just provide the basic info!
        </div>

        <div class="code-block">
            <pre><span class="syntax-comment">// Simple function to insert burial record (always Layer 1)</span>
<span class="syntax-string">function</span> <span class="syntax">insertBurialLayer1</span>(<span class="syntax-variable">$conn</span>, <span class="syntax-variable">$data</span>) {
    <span class="syntax-string">try</span> {
        <span class="syntax-comment">// Always use Layer 1</span>
        <span class="syntax-variable">$data</span>[<span class="syntax-string">'layer'</span>] = <span class="syntax-number">1</span>;
        
        <span class="syntax-comment">// Validate required fields</span>
        <span class="syntax-string">if</span> (!<span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>] || !<span class="syntax-variable">$data</span>[<span class="syntax-string">'full_name'</span>]) {
            <span class="syntax-string">throw new</span> <span class="syntax">Exception</span>(<span class="syntax-string">"lot_id and full_name are required"</span>);
        }
        
        <span class="syntax-comment">// Check if Layer 1 is available</span>
        <span class="syntax-variable">$stmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
            SELECT is_occupied FROM lot_layers 
            WHERE lot_id = :lot_id AND layer_number = 1
        "</span>);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
        <span class="syntax-variable">$stmt</span>-><span class="syntax">execute</span>();
        <span class="syntax-variable">$layerInfo</span> = <span class="syntax-variable">$stmt</span>-><span class="syntax">fetch</span>();
        
        <span class="syntax-string">if</span> (!<span class="syntax-variable">$layerInfo</span>) {
            <span class="syntax-comment">// Create Layer 1 if it doesn't exist</span>
            <span class="syntax-variable">$createStmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
                INSERT OR IGNORE INTO lot_layers (lot_id, layer_number, is_occupied)
                VALUES (:lot_id, 1, 0)
            "</span>);
            <span class="syntax-variable">$createStmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
            <span class="syntax-variable">$createStmt</span>-><span class="syntax">execute</span>();
        }
        
        <span class="syntax-comment">// Check if Layer 1 is occupied</span>
        <span class="syntax-variable">$checkStmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
            SELECT is_occupied FROM lot_layers 
            WHERE lot_id = :lot_id AND layer_number = 1
        "</span>);
        <span class="syntax-variable">$checkStmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
        <span class="syntax-variable">$checkStmt</span>-><span class="syntax">execute</span>();
        <span class="syntax-variable">$checkInfo</span> = <span class="syntax-variable">$checkStmt</span>-><span class="syntax">fetch</span>();
        
        <span class="syntax-string">if</span> (<span class="syntax-variable">$checkInfo</span> && <span class="syntax-variable">$checkInfo</span>[<span class="syntax-string">'is_occupied'</span>]) {
            <span class="syntax-string">throw new</span> <span class="syntax">Exception</span>(<span class="syntax-string">"Layer 1 is already occupied for this lot"</span>);
        }
        
        <span class="syntax-comment">// Start transaction</span>
        <span class="syntax-variable">$conn</span>-><span class="syntax">beginTransaction</span>();
        
        <span class="syntax-comment">// Insert burial record</span>
        <span class="syntax-variable">$stmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
            INSERT INTO deceased_records 
            (lot_id, layer, full_name, date_of_birth, date_of_death, date_of_burial, age, 
             cause_of_death, next_of_kin, next_of_kin_contact, remarks) 
            VALUES 
            (:lot_id, 1, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
             :cause_of_death, :next_of_kin, :next_of_kin_contact, :remarks)
        "</span>);
        
        <span class="syntax-variable">$stmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
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
            
            <span class="syntax-comment">// Update Layer 1 to mark it as occupied</span>
            <span class="syntax-variable">$updateStmt</span> = <span class="syntax-variable">$conn</span>-><span class="syntax">prepare</span>(<span class="syntax-string">"
                UPDATE lot_layers 
                SET is_occupied = 1, burial_record_id = :burial_record_id 
                WHERE lot_id = :lot_id AND layer_number = 1
            "</span>);
            <span class="syntax-variable">$updateStmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':burial_record_id'</span>, <span class="syntax-variable">$burialRecordId</span>);
            <span class="syntax-variable">$updateStmt</span>-><span class="syntax">bindParam</span>(<span class="syntax-string">':lot_id'</span>, <span class="syntax-variable">$data</span>[<span class="syntax-string">'lot_id'</span>]);
            <span class="syntax-variable">$updateStmt</span>-><span class="syntax">execute</span>();
            
            <span class="syntax-comment">// Update lot status to Occupied</span>
            <span class="syntax-variable">$conn</span>-><span class="syntax">exec</span>(<span class="syntax-string">"UPDATE cemetery_lots SET status = 'Occupied' WHERE id = {$data['lot_id']}"</span>);
            
            <span class="syntax-variable">$conn</span>-><span class="syntax">commit</span>();
            
            <span class="syntax-string">return</span> [
                <span class="syntax-string">'success'</span> => <span class="syntax-string">true</span>,
                <span class="syntax-string">'message'</span> => <span class="syntax-string">"✅ Added {$data['full_name']} to Layer 1"</span>
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

        <div class="code-block">
            <pre><span class="syntax-comment">// Quick usage example - just copy and run!</span>
<span class="syntax-string">&lt;?php</span>
<span class="syntax-string">require_once</span> <span class="syntax-string">'config/database.php'</span>;

<span class="syntax-variable">$database</span> = <span class="syntax-string">new</span> <span class="syntax">Database</span>();
<span class="syntax-variable">$conn</span> = <span class="syntax-variable">$database</span>-><span class="syntax">getConnection</span>();

<span class="syntax-comment">// Paste the insertBurialLayer1 function from above here</span>
<span class="syntax-comment">// ... (copy the function here)</span>

<span class="syntax-comment">// Your burial data - only need lot_id and full_name!</span>
<span class="syntax-variable">$burials</span> = [
    [
        <span class="syntax-string">'lot_id'</span> => <span class="syntax-number">1</span>,
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Juan Dela Cruz'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">75</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2023-12-01'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Maria Dela Cruz'</span>
    ],
    [
        <span class="syntax-string">'lot_id'</span> => <span class="syntax-number">2</span>,
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Maria Santos'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">82</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2023-11-15'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Jose Santos'</span>
    ],
    [
        <span class="syntax-string">'lot_id'</span> => <span class="syntax-number">3</span>,
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Antonio Reyes'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">68</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2024-01-10'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Luz Reyes'</span>
    ],
    [
        <span class="syntax-string">'lot_id'</span> => <span class="syntax-number">4</span>,
        <span class="syntax-string">'full_name'</span> => <span class="syntax-string">'Rosa Martinez'</span>,
        <span class="syntax-string">'age'</span> => <span class="syntax-number">91</span>,
        <span class="syntax-string">'date_of_death'</span> => <span class="syntax-string">'2024-02-01'</span>,
        <span class="syntax-string">'next_of_kin'</span> => <span class="syntax-string">'Carlos Martinez'</span>
    ]
];

<span class="syntax-comment">// Insert all burials to Layer 1</span>
<span class="syntax-string">foreach</span> (<span class="syntax-variable">$burials</span> <span class="syntax-string">as</span> <span class="syntax-variable">$burial</span>) {
    <span class="syntax-variable">$result</span> = <span class="syntax">insertBurialLayer1</span>(<span class="syntax-variable">$conn</span>, <span class="syntax-variable">$burial</span>);
    
    <span class="syntax-string">if</span> (<span class="syntax-variable">$result</span>[<span class="syntax-string">'success'</span>]) {
        <span class="syntax">echo</span> <span class="syntax-variable">$result</span>[<span class="syntax-string">'message'</span>] . <span class="syntax-string">"\n"</span>;
    } <span class="syntax-string">else</span> {
        <span class="syntax">echo</span> <span class="syntax-string">"❌ Error: {$result['message']}\n"</span>;
    }
}

<span class="syntax">echo</span> <span class="syntax-string">"\n🎉 All burials added to Layer 1!\n"</span>;
<span class="syntax-string">?&gt;</span></pre>
        </div>

        <div class="code-block">
            <h3>🏗️ Available Lots (for lot_id reference):</h3>
            <?php
            if ($conn) {
                try {
                    $stmt = $conn->query("SELECT id, lot_number, section, block FROM cemetery_lots ORDER BY lot_number");
                    $lots = $stmt->fetchAll();
                    
                    echo '<table>';
                    echo '<tr><th>lot_id</th><th>Lot Number</th><th>Section</th><th>Block</th></tr>';
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

        <div class="success">
            <strong>🎯 Key Features:</strong>
            <ul>
                <li>✅ All burials automatically go to Layer 1</li>
                <li>✅ Creates Layer 1 if it doesn't exist</li>
                <li>✅ Prevents double-booking Layer 1</li>
                <li>✅ Updates lot status to "Occupied"</li>
                <li>✅ Only need lot_id and full_name (other fields optional)</li>
            </ul>
        </div>
    </div>
</body>
</html>
