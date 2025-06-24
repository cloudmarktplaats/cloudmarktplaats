<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
<style>
.counter-box {
    width: 185px;
    background: linear-gradient(135deg, #232323 80%, #292929 100%);
    border-radius: 14px;
    font-family: 'Inter', Verdana, Arial, sans-serif;
    font-size: 13px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.18);
    padding: 10px 0 10px 0;
    border: 1.5px solid #2d2d2d;
}
.counter-btn-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 7px;
    gap: 6px;
    padding: 0 8px;
}
.counter-btn {
    background: #232323;
    color: #fff;
    border: 1.5px solid #444;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    flex: 1;
    margin: 0;
    padding: 4px 0;
    cursor: pointer;
    box-shadow: 0 1px 0 #111;
    transition: background 0.18s, border 0.18s, color 0.18s;
    letter-spacing: 0.02em;
}
.counter-btn:hover {
    background: #313131;
    border-color: #666;
    color: #ffc107;
}
.counter-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #232323;
    border: 1.5px solid #353535;
    border-radius: 8px;
    margin: 6px 8px;
    padding: 4px 10px 4px 10px;
    min-height: 30px;
    font-weight: 700;
    box-shadow: 0 1px 2px rgba(0,0,0,0.07);
    transition: border 0.18s, background 0.18s;
}
.counter-item:hover {
    border-color: #555;
    background: #262626;
}
.counter-item.credits { color: #ffc107; }
.counter-item.duckets { color: #ff7eb9; }
.counter-item.club { color: #00c3ff; }
.counter-item.belcredits { color: #b6aaff; }
.counter-item .label {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
}
.counter-item .value {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
}
.counter-item img {
    width: 18px;
    height: 18px;
    margin-left: 2px;
    filter: drop-shadow(0 1px 1px #0008);
}
</style>
<div class="counter-box">
    <div class="counter-btn-row">
        <button class="counter-btn">Help</button>
        <button class="counter-btn">Log uit</button>
    </div>
    <div class="counter-item credits">
        <span class="label">Credits</span>
        <span class="value">100000 <img src="https://i.imgur.com/0bX5R8A.png" alt="credits"></span>
    </div>
    <div class="counter-item duckets">
        <span class="label">Duckets</span>
        <span class="value">0 / 10000 <img src="https://i.imgur.com/1Q9Z1Zm.png" alt="duckets"></span>
    </div>
    <div class="counter-item club">
        <span class="label">HabLife Club</span>
        <span class="value">Word lid! <img src="https://i.imgur.com/2yQ4F5B.png" alt="club"></span>
    </div>
    <div class="counter-item belcredits">
        <span class="label">Belcredits</span>
        <span class="value">10 <img src="https://i.imgur.com/3yQ4F5B.png" alt="belcredits"></span>
    </div>
</div> 