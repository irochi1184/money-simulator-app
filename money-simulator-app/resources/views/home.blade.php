<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>引越しシミュレーター</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
  <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-7xl" x-data="simulator()" x-init="$nextTick(() => init())">
    <h1 class="text-3xl font-bold mb-8 text-center">引越しシミュレーター</h1>


    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
      <!-- 左: 入力欄 -->
      <div class="space-y-6 pr-2">
        <div class="space-y-4">
          <h2 class="text-xl font-semibold">固定費入力</h2>
          <template x-for="field in fields" :key="field.name">
            <div>
              <label class="block font-semibold mb-1" x-text="field.label"></label>
              <input type="range" :min="field.min" :max="field.max" :step="field.step" x-model.number="field.value" class="w-full mb-1">
              <input type="number" x-model.number="field.value" class="w-full p-2 border rounded text-right" step="field.step">
              <div class="text-right text-sm text-gray-500" x-text="field.value + ' 万円'"></div>
            </div>
          </template>
        </div>

        <div class="space-y-4">
          <h2 class="text-xl font-semibold mt-6">月別収入入力</h2>
          <template x-for="(income, index) in monthlyIncomes" :key="index">
            <div>
              <label class="block font-semibold mb-1" x-text="(index+1) + '月の収入 (万円)' "></label>
              <input type="number" x-model.number="monthlyIncomes[index]" class="w-full p-2 border rounded text-right" step="0.1">
            </div>
          </template>
        </div>

        <div class="space-y-4 mt-6">
          <h2 class="text-xl font-semibold">履歴保存メモ</h2>
          <input type="text" x-model="historyMemo" class="w-full p-2 border rounded" placeholder="例: 4月引越しシミュレーション">
        </div>

        <div class="text-center mt-6">
          <button @click="saveHistory" class="bg-yellow-500 text-white py-2 px-4 rounded hover:bg-yellow-600">履歴保存</button>
        </div>

        <div class="mt-6">
          <h2 class="text-xl font-semibold">履歴リスト</h2>
          <template x-for="(item, index) in histories" :key="index">
            <div class="flex justify-between items-center p-2 border rounded mt-2">
              <div>
                <div class="font-semibold" x-text="item.date"></div>
                <div class="text-sm text-gray-500" x-text="item.memo"></div>
              </div>
              <button @click="loadHistory(index)" class="bg-blue-400 text-white px-2 py-1 rounded">読み込み</button>
            </div>
          </template>
        </div>
      </div>


      <!-- 右: 結果表示 -->
      <div class="p-6 bg-green-50 rounded shadow space-y-6">
        <div>
          <h2 class="text-2xl font-bold mb-4">シミュレーション結果</h2>
          <div class="space-y-3">
            <p><strong>初期費用の目安:</strong> <span x-text="format(initialCost)"></span> 円</p>
            <p><strong>月々の支出目安:</strong> <span x-text="format(monthlyExpenses)"></span> 円</p>
            <p><strong>初期費用後の貯金残高:</strong> <span x-text="format(remainingSavings)"></span> 円</p>
          </div>
        </div>

        <div>
          <button @click="drawChart" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">グラフを更新</button>
          <canvas id="savingChart" class="mt-4 w-full h-64"></canvas>
        </div>

        <div>
          <h3 class="text-xl font-semibold mt-6 mb-2">年間推移（表形式）</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
              <thead>
                <tr>
                  <th class="px-2 py-1">月</th>
                  <th class="px-2 py-1">収入 (円)</th>
                  <th class="px-2 py-1">支出 (円)</th>
                  <th class="px-2 py-1">貯金残高 (円)</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="(item, index) in savingData" :key="index">
                  <tr>
                    <td class="px-2 py-1" x-text="(index+1) + '月'"></td>
                    <td class="px-2 py-1" x-text="format(item.income)"></td>
                    <td class="px-2 py-1" x-text="format(item.expense)"></td>
                    <td class="px-2 py-1" x-text="format(item.balance)"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <div class="text-center">
          <button @click="downloadPDF" class="bg-green-500 text-white py-2 px-4 rounded hover:bg-green-600 mt-6">PDFで保存</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function simulator() {
      return {
        fields: [
          { label: '家賃 (万円)', name: 'rent', value: 8.0, min: 0, max: 50, step: 0.1 },
          { label: '敷金 (ヶ月)', name: 'deposit', value: 1, min: 0, max: 6, step: 0.5 },
          { label: '礼金 (ヶ月)', name: 'key_money', value: 1, min: 0, max: 6, step: 0.5 },
          { label: '管理費 (万円)', name: 'management_fee', value: 0.5, min: 0, max: 5, step: 0.1 },
          { label: '貯金額 (万円)', name: 'savings', value: 100, min: 0, max: 1000, step: 1 },
          { label: '食費 (万円)', name: 'food_expense', value: 4, min: 0, max: 10, step: 0.1 },
          { label: '光熱費 (万円)', name: 'utility_expense', value: 1.5, min: 0, max: 5, step: 0.1 },
          { label: '通信費 (万円)', name: 'communication_expense', value: 1, min: 0, max: 5, step: 0.1 },
        ],
        monthlyIncomes: Array(12).fill(25),
        savingData: [],
        histories: [],
        historyMemo: '',
        savingChartInstance: null,

        get rent() { return this.fields.find(f => f.name === 'rent').value * 10000; },
        get deposit() { return this.fields.find(f => f.name === 'deposit').value; },
        get key_money() { return this.fields.find(f => f.name === 'key_money').value; },
        get managementFee() { return this.fields.find(f => f.name === 'management_fee').value * 10000; },
        get savings() { return this.fields.find(f => f.name === 'savings').value * 10000; },
        get foodExpense() { return this.fields.find(f => f.name === 'food_expense').value * 10000; },
        get utilityExpense() { return this.fields.find(f => f.name === 'utility_expense').value * 10000; },
        get communicationExpense() { return this.fields.find(f => f.name === 'communication_expense').value * 10000; },

        get initialCost() {
          return (this.rent * (this.deposit + this.key_money + 2)) + this.managementFee;
        },
        get monthlyExpenses() {
          return this.rent + this.managementFee + this.foodExpense + this.utilityExpense + this.communicationExpense;
        },
        get remainingSavings() {
          return this.savings - this.initialCost;
        },

        init() {
          const saved = localStorage.getItem('histories');
          if (saved) {
            this.histories = JSON.parse(saved);
          }
          this.$nextTick(() => {
            this.drawChart();
          });
        },

        drawChart() {
          // 既存グラフがあれば破棄
          if (this.savingChartInstance) {
            this.savingChartInstance.destroy();
            this.savingChartInstance = null;
          }

          const ctx = document.getElementById('savingChart').getContext('2d');

          // 最新フィールド値を取得し直す
          const rent = this.fields.find(f => f.name === 'rent').value * 10000;
          const deposit = this.fields.find(f => f.name === 'deposit').value;
          const key_money = this.fields.find(f => f.name === 'key_money').value;
          const managementFee = this.fields.find(f => f.name === 'management_fee').value * 10000;
          const savings = this.fields.find(f => f.name === 'savings').value * 10000;
          const foodExpense = this.fields.find(f => f.name === 'food_expense').value * 10000;
          const utilityExpense = this.fields.find(f => f.name === 'utility_expense').value * 10000;
          const communicationExpense = this.fields.find(f => f.name === 'communication_expense').value * 10000;

          const currentExpenses = rent + managementFee + foodExpense + utilityExpense + communicationExpense;
          const initialBalance = savings - (rent * (deposit + key_money + 2)) - managementFee;

          let balance = initialBalance;
          let updatedData = [];
          this.savingData = [];

          for (let i = 0; i < 12; i++) {
            const income = this.monthlyIncomes[i] * 10000;
            balance += income - currentExpenses;
            updatedData.push(balance);
            this.savingData.push({ income: income, expense: currentExpenses, balance: balance });
          }

          // ここで新しくチャート作成
          this.savingChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
              labels: [...Array(12)].map((_, i) => `${i + 1}月`),
              datasets: [{
                label: '貯金推移 (円)',
                data: updatedData,
                borderWidth: 2,
                fill: false,
                borderColor: 'rgb(75, 192, 192)'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: true } },
              scales: { y: { beginAtZero: true } }
            }
          });
        },

        saveHistory() {
          const now = new Date();
          const data = {
            fields: JSON.parse(JSON.stringify(this.fields)),
            monthlyIncomes: [...this.monthlyIncomes],
            memo: this.historyMemo,
            date: `${now.getFullYear()}-${now.getMonth()+1}-${now.getDate()} ${now.getHours()}:${now.getMinutes()}`
          };
          this.histories.push(data);
          localStorage.setItem('histories', JSON.stringify(this.histories));
          this.historyMemo = '';
        },

        loadHistory(index) {
          const history = this.histories[index];
          this.fields = JSON.parse(JSON.stringify(history.fields));
          this.monthlyIncomes = [...history.monthlyIncomes];
          this.drawChart();
        },

        downloadPDF() {
          html2canvas(document.querySelector('body')).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jspdf.jsPDF('p', 'mm', 'a4');
            const imgProps= pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            pdf.save('simulation_result.pdf');
          });
        },

        format(val) {
          return new Intl.NumberFormat().format(Math.round(val));
        },
      }
    }
  </script>
</body>
</html>