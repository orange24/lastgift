// LastGift donate.js
// 1) generate PromptPay QR (CRC16 + EMVCo) ฝั่ง browser ตาม amount ที่กรอก
// 2) เมื่อเลือกรูปสลิป → ลอง OCR ด้วย Tesseract.js เพื่อ auto-fill amount
// 3) copy เลขบัญชี

(function () {
  'use strict';
  if (!window.LG) return;

  // ---------- PromptPay payload builder ----------
  function tlv(id, value) {
    var l = String(value.length).padStart(2, '0');
    return id + l + value;
  }
  function crc16(s) {
    var crc = 0xFFFF;
    for (var i = 0; i < s.length; i++) {
      crc ^= (s.charCodeAt(i) << 8);
      for (var j = 0; j < 8; j++) {
        crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) : (crc << 1);
        crc &= 0xFFFF;
      }
    }
    return crc.toString(16).toUpperCase().padStart(4, '0');
  }
  function buildPayload(rawTarget, type, amount) {
    var target = String(rawTarget || '').replace(/\D+/g, '');
    var accId, subTag;
    if (type === 'phone') {
      target = target.replace(/^0+/, '');
      if (target.length >= 11 && target.substr(0, 2) === '66') {
        target = target.substr(2);
      }
      accId = ('0066' + target.padStart(9, '0'));
      accId = accId.padStart(13, '0').slice(-13);
      subTag = '01';
    } else if (type === 'nid') {
      accId = target.padStart(13, '0');
      subTag = '02';
    } else {
      accId = target.padStart(15, '0');
      subTag = '03';
    }
    var merchant = tlv('00', 'A000000677010111') + tlv(subTag, accId);
    var hasAmt = amount && Number(amount) > 0;
    var p =
      tlv('00', '01') +
      tlv('01', hasAmt ? '12' : '11') +
      tlv('29', merchant) +
      tlv('53', '764') +
      (hasAmt ? tlv('54', Number(amount).toFixed(2)) : '') +
      tlv('58', 'TH');
    p += '6304';
    return p + crc16(p);
  }

  // ---------- render QR (only in promptpay mode) ----------
  var qrBox = document.getElementById('qrcode');
  var amountInput = document.getElementById('amount');
  var amountLabel = document.getElementById('qrAmountLabel');
  var qrCanvas = null;

  function renderQR() {
    if (LG.qrMode !== 'promptpay') return;
    if (!qrBox || !window.QRCode) return;
    var amt = amountInput && amountInput.value ? Number(amountInput.value) : 0;
    var payload = buildPayload(LG.ppId, LG.ppType, amt > 0 ? amt : null);
    if (amountLabel) amountLabel.textContent = amt > 0 ? '฿' + amt.toFixed(2) : 'ไม่ระบุ (สแกนแล้วใส่ยอดเอง)';
    qrBox.innerHTML = '';
    qrCanvas = document.createElement('canvas');
    qrBox.appendChild(qrCanvas);
    QRCode.toCanvas(qrCanvas, payload, { width: 220, margin: 1, errorCorrectionLevel: 'M' }, function (err) {
      if (err) console.warn(err);
    });
  }
  renderQR();
  if (amountInput && LG.qrMode === 'promptpay') {
    var t = null;
    amountInput.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(renderQR, 250);
    });
  }

  // ---------- OCR slip → auto-fill amount + time ----------
  var slipInput = document.getElementById('slip');
  var ocrStatus = document.getElementById('ocrStatus');
  var timeInput = document.getElementById('transferredAt');
  if (slipInput) {
    slipInput.addEventListener('change', function () {
      var file = slipInput.files && slipInput.files[0];
      if (!file) return;
      if (!window.Tesseract) return;
      var amtHas = amountInput && amountInput.value && Number(amountInput.value) > 0;
      // ช่องเวลามี default = now เสมอ → OCR overwrite ได้ถ้าหาเจอเวลาจากสลิป (แม่นกว่า)
      if (ocrStatus) ocrStatus.textContent = 'กำลังลองอ่านสลิป...';
      Tesseract.recognize(file, 'eng').then(function (res) {
        var text = (res && res.data && res.data.text) || '';
        console.log('[LG OCR] raw text:\n', text);
        var found = [];
        // amount — ไม่ทับถ้า user กรอกมาแล้ว
        if (!amtHas) {
          var amt = parseAmount(text);
          if (amt && amountInput) {
            amountInput.value = amt.toFixed(2);
            renderQR();
            found.push('ยอด ฿' + amt.toFixed(2));
          }
        }
        // time — overwrite default "now" ด้วยเวลาในสลิป
        var dt = parseDateTime(text);
        if (dt && timeInput) {
          timeInput.value = dt;
          found.push('เวลา ' + dt.replace('T', ' '));
        }
        if (ocrStatus) {
          ocrStatus.textContent = found.length
            ? 'อ่านได้: ' + found.join(', ') + ' — ตรวจกับสลิปอีกครั้งและแก้ได้ถ้าผิด'
            : 'อ่านสลิปไม่ได้ กรุณากรอกเอง';
        }
      }).catch(function (e) {
        console.warn(e);
        if (ocrStatus) ocrStatus.textContent = 'อ่านสลิปไม่สำเร็จ กรุณากรอกเอง';
      });
    });
  }

  // หาเวลา + วันที่จากข้อความ OCR ของสลิป
  // คืนค่าเป็น "YYYY-MM-DDTHH:MM" หรือ null
  function parseDateTime(text) {
    if (!text) return null;
    var hh = null, mm = null, y = null, mo = null, d = null;

    // normalize OCR errors: I/l → 1, O → 0 (เฉพาะเมื่อมีตัวเลขติดกัน)
    // ใช้ lookahead เท่านั้น (Safari เก่ารองรับ lookbehind ไม่ครบ)
    var norm = text
      .replace(/([IlOo])(?=\d)/g, function (_, c) {
        return (c === 'O' || c === 'o') ? '0' : '1';
      })
      .replace(/(\d)([IlOo])/g, function (_, d, c) {
        return d + ((c === 'O' || c === 'o') ? '0' : '1');
      });

    // เวลา — รองรับ 3 patterns
    // 1) มี "น." ต่อท้าย (แม่นสุด)
    var tm = norm.match(/([01]?\d|2[0-3])[:\.\s]([0-5]\d)\s*น\.?/);
    // 2) HH:MM ที่มี ":"
    if (!tm) tm = norm.match(/\b([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?\b/);
    // 3) HH.MM ที่ตามด้วย เลข 2 หลัก แล้วไม่มี "บาท" (มัก seconds เช่น 13.48.30)
    if (!tm) tm = norm.match(/\b([01]?\d|2[0-3])\.([0-5]\d)\.[0-5]\d\b/);
    if (tm) { hh = parseInt(tm[1], 10); mm = parseInt(tm[2], 10); }
    console.log('[LG OCR] normalized:\n', norm);
    console.log('[LG OCR] time match:', tm);
    if (hh === null) return null;

    // วันที่ — รองรับหลายแบบ
    // 1) "20 พ.ค. 69" / "20 May 69" / "20 May 2026" / "20 พฤษภาคม 2569"
    var monthsTh = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    var monthsThFull = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    var monthsEn = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];

    var dm = text.match(/\b(\d{1,2})\s*([฀-๿a-zA-Z\.]+)\s*(\d{2,4})\b/);
    if (dm) {
      var dCand = parseInt(dm[1], 10);
      var monStr = dm[2].toLowerCase().replace(/\./g, '');
      var idx = -1;
      for (var i = 0; i < monthsEn.length; i++) {
        var en = monthsEn[i];
        var th  = monthsTh[i].replace(/\./g, '');
        var thf = monthsThFull[i];
        if (monStr.indexOf(en) === 0 || monStr === th || monStr === thf) { idx = i; break; }
      }
      if (idx >= 0) {
        d = dCand;
        mo = idx + 1;
        y = parseInt(dm[3], 10);
        if (y < 100) y = (y >= 50 ? 2500 + y : 2000 + y);
        if (y >= 2400) y -= 543;
      } else {
        // OCR เห็น day + year แต่ month อ่านไม่ออก → ใช้เดือนปัจจุบัน
        var yCand = parseInt(dm[3], 10);
        if (yCand < 100) yCand = (yCand >= 50 ? 2500 + yCand : 2000 + yCand);
        if (yCand >= 2400) yCand -= 543;
        if (yCand >= 2000 && yCand <= 2100 && dCand >= 1 && dCand <= 31) {
          d = dCand;
          y = yCand;
          mo = (new Date()).getMonth() + 1;
          console.log('[LG OCR] month unreadable — using current month', mo);
        }
      }
    }
    // 2) "20/05/26" หรือ "20-05-2026"
    if (d === null) {
      var dm2 = text.match(/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/);
      if (dm2) {
        d  = parseInt(dm2[1], 10);
        mo = parseInt(dm2[2], 10);
        y  = parseInt(dm2[3], 10);
        if (y < 100) y = (y >= 50 ? 2500 + y : 2000 + y);
        if (y >= 2400) y -= 543;
      }
    }

    // fallback: ถ้าหาวันที่ไม่ได้เลย ใช้วันนี้ (สลิปมักเป็นวันนี้)
    if (d === null || mo === null || y === null) {
      var now = new Date();
      d = now.getDate(); mo = now.getMonth() + 1; y = now.getFullYear();
      console.log('[LG OCR] date fallback to today');
    }
    if (y < 2000 || y > 2100) return null;
    if (mo < 1 || mo > 12 || d < 1 || d > 31) return null;
    var out = pad(y, 4) + '-' + pad(mo, 2) + '-' + pad(d, 2) + 'T' + pad(hh, 2) + ':' + pad(mm, 2);
    console.log('[LG OCR] datetime built:', out);
    return out;
  }
  function pad(n, len) { var s = String(n); while (s.length < len) s = '0' + s; return s; }

  // เลือก amount ที่น่าจะเป็นยอดเงิน — แม่นกว่า regex ตัวเลขล้วน
  function parseAmount(text) {
    if (!text) return null;
    // หา pattern ตัวเลขมี comma หรือ . (สองหลักทศนิยม) เช่น 1,500.00 / 500.00 / 1500.00
    var re = /(\d{1,3}(?:,\d{3})+\.\d{2}|\d+\.\d{2})/g;
    var matches = [];
    var m;
    while ((m = re.exec(text)) !== null) {
      var raw = m[1].replace(/,/g, '');
      var val = parseFloat(raw);
      if (val > 0 && val < 10000000) matches.push(val);
    }
    if (!matches.length) {
      // fallback: ตัวเลขล้วนไม่มีจุด
      var re2 = /\b(\d{2,7})\b/g;
      while ((m = re2.exec(text)) !== null) {
        var v = parseInt(m[1], 10);
        if (v >= 10 && v < 1000000) matches.push(v);
      }
    }
    if (!matches.length) return null;
    // เลือกค่าที่มากสุด — สลิปธนาคารยอดเงินมักเป็นตัวเลข amount ที่ใหญ่ที่สุด
    // (เลขอ้างอิงมักไม่มี .00 จะถูกตัดออกใน pass แรก)
    return Math.max.apply(null, matches);
  }

  // ---------- download PromptPay QR (canvas → png) ----------
  var dlBtn = document.getElementById('downloadQR');
  if (dlBtn) {
    dlBtn.addEventListener('click', function () {
      var canvas = document.querySelector('#qrcode canvas');
      if (!canvas) return;
      var amt = amountInput && amountInput.value ? Number(amountInput.value) : 0;
      var fname = 'promptpay' + (amt > 0 ? '-' + amt.toFixed(0) + 'baht' : '') + '.png';
      var url = canvas.toDataURL('image/png');
      var a = document.createElement('a');
      a.href = url;
      a.download = fname;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    });
  }

  // ---------- copy เลขบัญชี ----------
  document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var t = document.getElementById(btn.getAttribute('data-copy-target'));
      if (!t) return;
      var text = t.textContent.trim();
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () { flashBtn(btn); });
      } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        flashBtn(btn);
      }
    });
  });
  function flashBtn(btn) {
    var orig = btn.textContent;
    btn.textContent = 'คัดลอกแล้ว ✓';
    setTimeout(function () { btn.textContent = orig; }, 1500);
  }
})();
