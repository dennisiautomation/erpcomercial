/**
 * ERP COMERCIAL — Core Intelligence Layer
 * Autocomplete, Masks, ViaCEP, CNPJ Lookup, Import CSV,
 * Smart Forms, Keyboard Navigation, Real-time Calculations
 */
const ERP = {
    // ─── MASKS ──────────────────────────────────────────────
    masks: {
        cpf(v) { return v.replace(/\D/g,'').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2').slice(0,14); },
        cnpj(v) { return v.replace(/\D/g,'').replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2').slice(0,18); },
        cpfCnpj(v) { const d = v.replace(/\D/g,''); return d.length <= 11 ? ERP.masks.cpf(v) : ERP.masks.cnpj(v); },
        cep(v) { return v.replace(/\D/g,'').replace(/(\d{5})(\d)/,'$1-$2').slice(0,9); },
        telefone(v) { const d = v.replace(/\D/g,''); return d.length <= 10 ? d.replace(/(\d{2})(\d)/,'($1) $2').replace(/(\d{4})(\d)/,'$1-$2') : d.replace(/(\d{2})(\d)/,'($1) $2').replace(/(\d{5})(\d)/,'$1-$2'); },
        money(v) { const n = parseFloat(v.replace(/\D/g,''))/100; return isNaN(n) ? '' : n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); },
        ncm(v) { return v.replace(/\D/g,'').replace(/(\d{4})(\d)/,'$1.$2').replace(/(\d{2})(\d)/,'$1.$2').slice(0,10); },
    },

    initMasks() {
        document.querySelectorAll('[data-mask]').forEach(el => {
            const mask = el.dataset.mask;
            if (ERP.masks[mask]) {
                el.addEventListener('input', () => {
                    const pos = el.selectionStart;
                    const prev = el.value.length;
                    el.value = ERP.masks[mask](el.value);
                    const diff = el.value.length - prev;
                    el.setSelectionRange(pos + diff, pos + diff);
                });
            }
        });
    },

    // ─── VIACEP ─────────────────────────────────────────────
    async buscaCEP(cep, prefix = '') {
        const clean = cep.replace(/\D/g, '');
        if (clean.length !== 8) return null;

        const btn = document.querySelector(`[data-cep-btn${prefix ? '="'+prefix+'"' : ''}]`);
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

        try {
            const res = await fetch(`https://viacep.com.br/ws/${clean}/json/`);
            const data = await res.json();
            if (data.erro) throw new Error('CEP não encontrado');

            const map = { logradouro: 'logradouro', bairro: 'bairro', localidade: 'cidade', uf: 'uf' };
            for (const [api, field] of Object.entries(map)) {
                const el = document.querySelector(`[name="${prefix}${field}"]`);
                if (el && data[api]) { el.value = data[api]; el.dispatchEvent(new Event('change')); }
            }
            // Focus no número após preencher
            const numEl = document.querySelector(`[name="${prefix}numero"]`);
            if (numEl) numEl.focus();

            return data;
        } catch (e) {
            ERP.toast('CEP não encontrado', 'warning');
            return null;
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-search"></i>'; }
        }
    },

    initCEP() {
        document.querySelectorAll('[data-cep]').forEach(el => {
            const prefix = el.dataset.cep || '';
            el.addEventListener('blur', () => ERP.buscaCEP(el.value, prefix));
            // Botão de busca ao lado
            const btn = el.parentElement?.querySelector('[data-cep-btn]');
            if (btn) btn.addEventListener('click', () => ERP.buscaCEP(el.value, prefix));
        });
    },

    // ─── CNPJ LOOKUP (BrasilAPI) ────────────────────────────
    async buscaCNPJ(cnpj, prefix = '') {
        const clean = cnpj.replace(/\D/g, '');
        if (clean.length !== 14) return null;

        const indicator = document.querySelector('[data-cnpj-loading]');
        if (indicator) indicator.classList.remove('d-none');

        try {
            const res = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${clean}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('not found');
            const data = await res.json();

            const map = {
                razao_social: 'razao_social', nome_fantasia: 'nome_fantasia',
                cep: 'cep', logradouro: 'logradouro', numero: 'numero',
                complemento: 'complemento', bairro: 'bairro',
                municipio: 'cidade', uf: 'uf',
                ddd_telefone_1: 'telefone', email: 'email'
            };

            for (const [api, field] of Object.entries(map)) {
                const el = document.querySelector(`[name="${prefix}${field}"]`);
                if (el && data[api]) {
                    el.value = data[api];
                    el.dispatchEvent(new Event('change'));
                    el.classList.add('border-success');
                    setTimeout(() => el.classList.remove('border-success'), 2000);
                }
            }

            ERP.toast(`CNPJ encontrado: ${data.razao_social}`, 'success');
            return data;
        } catch (e) {
            ERP.toast('CNPJ não encontrado na Receita Federal', 'warning');
            return null;
        } finally {
            if (indicator) indicator.classList.add('d-none');
        }
    },

    initCNPJ() {
        document.querySelectorAll('[data-cnpj-lookup]').forEach(el => {
            const prefix = el.dataset.cnpjLookup || '';
            let timeout;
            el.addEventListener('blur', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => ERP.buscaCNPJ(el.value, prefix), 300);
            });
        });
    },

    // ─── AUTOCOMPLETE SEARCH ────────────────────────────────
    initAutocomplete() {
        document.querySelectorAll('[data-autocomplete]').forEach(el => {
            const url = el.dataset.autocomplete;
            const targetId = el.dataset.autocompleteTarget;
            const displayField = el.dataset.autocompleteDisplay || 'nome';
            const valueField = el.dataset.autocompleteValue || 'id';
            const extraFields = el.dataset.autocompleteExtra ? JSON.parse(el.dataset.autocompleteExtra) : {};

            let dropdown = el.parentElement.querySelector('.autocomplete-dropdown');
            if (!dropdown) {
                dropdown = document.createElement('div');
                dropdown.className = 'autocomplete-dropdown';
                el.parentElement.style.position = 'relative';
                el.parentElement.appendChild(dropdown);
            }

            let debounce;
            el.addEventListener('input', () => {
                clearTimeout(debounce);
                const q = el.value.trim();
                if (q.length < 2) { dropdown.innerHTML = ''; dropdown.style.display = 'none'; return; }

                dropdown.innerHTML = '<div class="autocomplete-empty"><span class="spinner-border spinner-border-sm me-2"></span>Buscando...</div>';
                dropdown.style.display = 'block';

                debounce = setTimeout(async () => {
                    try {
                        const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
                        });
                        const items = await res.json();

                        if (!items.length) {
                            dropdown.innerHTML = '<div class="autocomplete-empty">Nenhum resultado</div>';
                            dropdown.style.display = 'block';
                            return;
                        }

                        dropdown.innerHTML = items.map(item => `
                            <div class="autocomplete-item" data-value="${item[valueField]}" data-json='${JSON.stringify(item)}'>
                                <strong>${item[displayField]}</strong>
                                ${item.cpf_cnpj ? `<small class="text-muted ms-2">${item.cpf_cnpj}</small>` : ''}
                            </div>
                        `).join('');
                        dropdown.style.display = 'block';

                        dropdown.querySelectorAll('.autocomplete-item').forEach(opt => {
                            opt.addEventListener('click', () => {
                                const data = JSON.parse(opt.dataset.json);
                                el.value = data[displayField];
                                if (targetId) document.getElementById(targetId).value = data[valueField];
                                // Preencher campos extras
                                for (const [dataKey, selector] of Object.entries(extraFields)) {
                                    const target = document.querySelector(selector);
                                    if (target && data[dataKey]) target.value = data[dataKey];
                                }
                                dropdown.style.display = 'none';
                                el.dispatchEvent(new Event('autocomplete-select', { bubbles: true }));
                            });
                        });
                    } catch (e) {
                        console.error('Autocomplete error:', e);
                        dropdown.innerHTML = '<div class="autocomplete-empty text-danger">Erro ao buscar. Tente novamente.</div>';
                    }
                }, 300);
            });

            // Fechar dropdown ao clicar fora
            document.addEventListener('click', (e) => {
                if (!el.parentElement.contains(e.target)) dropdown.style.display = 'none';
            });

            // Navegação por teclado
            el.addEventListener('keydown', (e) => {
                const items = dropdown.querySelectorAll('.autocomplete-item');
                const active = dropdown.querySelector('.autocomplete-item.active');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const next = active ? active.nextElementSibling : items[0];
                    if (active) active.classList.remove('active');
                    if (next) next.classList.add('active');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prev = active ? active.previousElementSibling : items[items.length-1];
                    if (active) active.classList.remove('active');
                    if (prev) prev.classList.add('active');
                } else if (e.key === 'Enter' && active) {
                    e.preventDefault();
                    active.click();
                } else if (e.key === 'Escape') {
                    dropdown.style.display = 'none';
                }
            });
        });
    },

    // ─── MARKUP / PRICE CALCULATOR ──────────────────────────
    initPriceCalc() {
        const custo = document.querySelector('[data-price="custo"]');
        const markup = document.querySelector('[data-price="markup"]');
        const venda = document.querySelector('[data-price="venda"]');
        if (!custo || !markup || !venda) return;

        const calc = () => {
            const c = parseFloat(custo.value) || 0;
            const m = parseFloat(markup.value) || 0;
            if (c > 0 && m > 0) {
                venda.value = (c * (1 + m / 100)).toFixed(2);
            }
        };
        const calcReverse = () => {
            const c = parseFloat(custo.value) || 0;
            const v = parseFloat(venda.value) || 0;
            if (c > 0 && v > 0) {
                markup.value = (((v - c) / c) * 100).toFixed(2);
            }
        };

        custo.addEventListener('input', calc);
        markup.addEventListener('input', calc);
        venda.addEventListener('input', calcReverse);
    },

    // ─── DYNAMIC ITEM ROWS ──────────────────────────────────
    initDynamicItems() {
        document.querySelectorAll('[data-dynamic-items]').forEach(container => {
            const template = container.querySelector('[data-item-template]');
            if (!template) return;
            const tbody = container.querySelector('tbody') || container;
            const addBtn = container.querySelector('[data-add-item]');
            let index = tbody.querySelectorAll('tr[data-item-row]').length;

            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    const row = template.content.cloneNode(true);
                    // Replace __INDEX__ placeholder
                    row.querySelectorAll('[name]').forEach(el => {
                        el.name = el.name.replace(/__INDEX__/g, index);
                    });
                    tbody.appendChild(row);
                    index++;
                    ERP.recalcTotals(container);
                    // Focus first input
                    const firstInput = tbody.lastElementChild?.querySelector('input');
                    if (firstInput) firstInput.focus();
                });
            }

            // Remove item
            container.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('[data-remove-item]');
                if (removeBtn) {
                    removeBtn.closest('tr[data-item-row]')?.remove();
                    ERP.recalcTotals(container);
                }
            });

            // Recalc on input
            container.addEventListener('input', (e) => {
                if (e.target.matches('[data-calc]')) ERP.recalcTotals(container);
            });
        });
    },

    recalcTotals(container) {
        let subtotal = 0;
        container.querySelectorAll('tr[data-item-row]').forEach((row, i) => {
            const qty = parseFloat(row.querySelector('[data-calc="qty"]')?.value) || 0;
            const price = parseFloat(row.querySelector('[data-calc="price"]')?.value) || 0;
            const discount = parseFloat(row.querySelector('[data-calc="discount"]')?.value) || 0;
            const total = Math.max(0, (qty * price) - discount);
            const totalEl = row.querySelector('[data-calc="total"]');
            if (totalEl) totalEl.value = total.toFixed(2);
            // Update row number
            const numEl = row.querySelector('[data-item-num]');
            if (numEl) numEl.textContent = i + 1;
            subtotal += total;
        });

        const subtotalEl = container.closest('form')?.querySelector('[data-subtotal]');
        if (subtotalEl) subtotalEl.textContent = ERP.formatMoney(subtotal);

        const descPerc = parseFloat(container.closest('form')?.querySelector('[data-discount-perc]')?.value) || 0;
        const descVal = parseFloat(container.closest('form')?.querySelector('[data-discount-val]')?.value) || 0;
        const discount = descVal > 0 ? descVal : (subtotal * descPerc / 100);
        const total = Math.max(0, subtotal - discount);

        const discountEl = container.closest('form')?.querySelector('[data-discount-display]');
        if (discountEl) discountEl.textContent = ERP.formatMoney(discount);
        const totalEl = container.closest('form')?.querySelector('[data-total]');
        if (totalEl) totalEl.textContent = ERP.formatMoney(total);
    },

    // ─── CSV/EXCEL IMPORT ───────────────────────────────────
    initImport() {
        document.querySelectorAll('[data-import]').forEach(btn => {
            const url = btn.dataset.import;
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.csv,.xlsx,.xls';
            fileInput.style.display = 'none';
            document.body.appendChild(fileInput);

            btn.addEventListener('click', () => fileInput.click());

            fileInput.addEventListener('change', async () => {
                if (!fileInput.files.length) return;
                const file = fileInput.files[0];
                const formData = new FormData();
                formData.append('arquivo', file);

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importando...';

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
                    });
                    const data = await res.json();
                    if (data.success) {
                        ERP.toast(`${data.imported || 0} registros importados com sucesso!`, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        ERP.toast(data.error || 'Erro na importação', 'danger');
                    }
                } catch (e) {
                    ERP.toast('Erro ao importar arquivo', 'danger');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-upload me-1"></i> Importar';
                    fileInput.value = '';
                }
            });
        });
    },

    // ─── BATCH OPERATIONS ───────────────────────────────────
    initBatch() {
        document.querySelectorAll('[data-batch]').forEach(container => {
            const selectAll = container.querySelector('[data-batch-all]');
            const checkboxes = () => container.querySelectorAll('[data-batch-item]');
            const toolbar = container.querySelector('[data-batch-toolbar]');
            const countEl = container.querySelector('[data-batch-count]');

            const updateToolbar = () => {
                const checked = container.querySelectorAll('[data-batch-item]:checked').length;
                if (toolbar) toolbar.style.display = checked > 0 ? 'flex' : 'none';
                if (countEl) countEl.textContent = checked;
            };

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    checkboxes().forEach(cb => { cb.checked = selectAll.checked; });
                    updateToolbar();
                });
            }

            container.addEventListener('change', (e) => {
                if (e.target.matches('[data-batch-item]')) updateToolbar();
            });
        });
    },

    // ─── PARCELAS GENERATOR ─────────────────────────────────
    initParcelas() {
        const container = document.querySelector('[data-parcelas]');
        if (!container) return;

        const valorEl = container.querySelector('[data-parcelas-valor]');
        const qtdEl = container.querySelector('[data-parcelas-qtd]');
        const vencEl = container.querySelector('[data-parcelas-vencimento]');
        const preview = container.querySelector('[data-parcelas-preview]');

        const generate = () => {
            const total = parseFloat(valorEl?.value) || 0;
            const qtd = parseInt(qtdEl?.value) || 1;
            const venc = vencEl?.value;
            if (!total || !venc || !preview) return;

            const valorParcela = Math.floor(total / qtd * 100) / 100;
            const resto = Math.round((total - valorParcela * qtd) * 100) / 100;

            let html = '<table class="table table-sm mb-0"><thead><tr><th>#</th><th>Vencimento</th><th>Valor</th></tr></thead><tbody>';
            for (let i = 0; i < qtd; i++) {
                const d = new Date(venc + 'T00:00:00');
                d.setMonth(d.getMonth() + i);
                const val = i === 0 ? valorParcela + resto : valorParcela;
                html += `<tr><td>${i+1}/${qtd}</td><td>${d.toLocaleDateString('pt-BR')}</td><td>R$ ${val.toFixed(2)}</td></tr>`;
            }
            html += '</tbody></table>';
            preview.innerHTML = html;
        };

        [valorEl, qtdEl, vencEl].forEach(el => el?.addEventListener('input', generate));
        generate();
    },

    // ─── TOAST NOTIFICATIONS ────────────────────────────────
    toast(message, type = 'info', opts = {}) {
        let container = document.getElementById('erp-toasts');
        if (!container) {
            container = document.createElement('div');
            container.id = 'erp-toasts';
            container.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;max-width:420px;';
            document.body.appendChild(container);
        }

        const icons = { success: 'check-circle-fill', danger: 'exclamation-triangle-fill', warning: 'exclamation-circle-fill', info: 'info-circle-fill' };
        const colors = { success: '#059669', danger: '#dc2626', warning: '#d97706', info: '#0891b2' };
        const duration = opts.duration !== undefined ? opts.duration : (type === 'danger' ? 8000 : 4000);

        const toast = document.createElement('div');
        toast.style.cssText = `background:#fff;border-left:4px solid ${colors[type]||colors.info};border-radius:0.5rem;padding:0.75rem 0.875rem;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:flex;align-items:flex-start;gap:0.5rem;min-width:280px;animation:slideIn 0.3s ease;font-size:0.875rem;`;

        const iconEl = document.createElement('i');
        iconEl.className = `bi bi-${icons[type]||icons.info}`;
        iconEl.style.cssText = `color:${colors[type]||colors.info};flex-shrink:0;margin-top:2px;`;

        const msgEl = document.createElement('div');
        msgEl.style.cssText = 'flex:1;line-height:1.4;word-break:break-word;';
        if (opts.title) {
            const titleEl = document.createElement('div');
            titleEl.style.cssText = 'font-weight:600;margin-bottom:2px;';
            titleEl.textContent = opts.title;
            msgEl.appendChild(titleEl);
        }
        const textEl = document.createElement('div');
        textEl.textContent = message;
        msgEl.appendChild(textEl);

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Fechar');
        closeBtn.style.cssText = 'background:none;border:0;padding:0;line-height:1;cursor:pointer;color:#6b7280;font-size:1.25rem;flex-shrink:0;';
        closeBtn.innerHTML = '&times;';

        const dismiss = () => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.3s';
            setTimeout(() => toast.remove(), 300);
        };
        closeBtn.addEventListener('click', dismiss);

        toast.appendChild(iconEl);
        toast.appendChild(msgEl);
        toast.appendChild(closeBtn);
        container.appendChild(toast);

        if (duration > 0) setTimeout(dismiss, duration);
        return { dismiss };
    },

    // ─── HELPERS ─────────────────────────────────────────────
    formatMoney(v) { return 'R$ ' + parseFloat(v).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}); },

    // ─── KEYBOARD SHORTCUTS ─────────────────────────────────
    initKeyboard() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+Enter = submit form
            if (e.ctrlKey && e.key === 'Enter') {
                const form = document.querySelector('form.erp-form, form[data-form]');
                if (form) { e.preventDefault(); form.requestSubmit(); }
            }
            // Escape = close modal
            if (e.key === 'Escape') {
                const modal = document.querySelector('.modal.show');
                if (modal) bootstrap.Modal.getInstance(modal)?.hide();
            }
        });
    },

    // ─── CONFIRM (modal Bootstrap) ──────────────────────────
    confirm(opts = {}) {
        const isDanger = opts.variant === 'danger' || opts.danger === true;
        const modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header ${isDanger ? 'bg-danger text-white' : ''}">
                        <h5 class="modal-title">
                            <i class="bi bi-${opts.icon || (isDanger ? 'exclamation-triangle-fill' : 'question-circle')} me-2"></i>
                            <span data-role="title"></span>
                        </h5>
                        <button type="button" class="btn-close ${isDanger ? 'btn-close-white' : ''}" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0" data-role="message"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-role="cancel"></button>
                        <button type="button" class="btn btn-${isDanger ? 'danger' : 'primary'}" data-role="ok"></button>
                    </div>
                </div>
            </div>`;
        modalEl.querySelector('[data-role="title"]').textContent = opts.title || 'Confirmar';
        modalEl.querySelector('[data-role="message"]').textContent = opts.message || 'Tem certeza?';
        modalEl.querySelector('[data-role="cancel"]').textContent = opts.cancelText || 'Cancelar';
        const okBtn = modalEl.querySelector('[data-role="ok"]');
        okBtn.innerHTML = `<i class="bi bi-${isDanger ? 'trash' : 'check-lg'} me-1"></i>${opts.confirmText || (isDanger ? 'Excluir' : 'Confirmar')}`;

        document.body.appendChild(modalEl);
        const bsModal = new bootstrap.Modal(modalEl);

        return new Promise(resolve => {
            let decided = false;
            okBtn.addEventListener('click', () => { decided = true; bsModal.hide(); resolve(true); });
            modalEl.addEventListener('hidden.bs.modal', () => {
                if (!decided) resolve(false);
                modalEl.remove();
            });
            bsModal.show();
            setTimeout(() => okBtn.focus(), 200);
        });
    },

    // ─── CONFIRM DELETE / DATA-CONFIRM ──────────────────────
    initConfirmDelete() {
        // Intercepta submit com data-confirm no form ou DELETE
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.dataset.erpConfirmed === '1') return;

            const customMsg = form.dataset.confirm;
            const isDelete = form.method && form.method.toLowerCase() === 'post'
                && form.querySelector('input[name="_method"][value="DELETE"]');

            if (!customMsg && !isDelete) return;

            e.preventDefault();
            ERP.confirm({
                title: form.dataset.confirmTitle || (isDelete ? 'Confirmar exclusão' : 'Confirmar ação'),
                message: customMsg || 'Tem certeza que deseja excluir este registro?',
                variant: isDelete || form.dataset.confirmVariant === 'danger' ? 'danger' : 'primary',
                confirmText: form.dataset.confirmOk || (isDelete ? 'Excluir' : 'Confirmar'),
            }).then(ok => {
                if (ok) {
                    form.dataset.erpConfirmed = '1';
                    form.submit();
                }
            });
        }, true);

        // Intercepta click em [data-confirm] (buttons, links)
        document.addEventListener('click', (e) => {
            const el = e.target.closest('[data-confirm]');
            if (!el) return;
            if (el.tagName === 'FORM') return; // form é tratado no submit
            if (el.dataset.erpConfirmed === '1') return;

            const isDanger = el.classList.contains('btn-danger') || el.classList.contains('btn-outline-danger')
                || el.dataset.confirmVariant === 'danger';

            e.preventDefault();
            e.stopPropagation();

            ERP.confirm({
                title: el.dataset.confirmTitle || 'Confirmar ação',
                message: el.dataset.confirm,
                variant: isDanger ? 'danger' : 'primary',
                confirmText: el.dataset.confirmOk || 'Confirmar',
            }).then(ok => {
                if (!ok) return;
                el.dataset.erpConfirmed = '1';
                // Se é submit button dentro de form, submete o form
                if (el.tagName === 'BUTTON' && el.type === 'submit' && el.form) {
                    el.form.dataset.erpConfirmed = '1';
                    el.form.requestSubmit(el);
                } else if (el.tagName === 'A' && el.href) {
                    window.location.href = el.href;
                } else {
                    el.click();
                }
            });
        }, true);
    },

    // ─── LOADING STATE EM SUBMIT ────────────────────────────
    initSubmitLoading() {
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form.matches('form')) return;
            if (form.dataset.erpNoLoading === '1') return;
            if (form.dataset.erpConfirmed === '0' && form.dataset.confirm) return; // será interceptado

            const btn = form.querySelector('button[type="submit"]:not([data-no-loading])');
            if (!btn || btn.disabled) return;

            const original = btn.innerHTML;
            const icon = btn.querySelector('i.bi');
            if (icon) {
                icon.className = 'spinner-border spinner-border-sm me-1';
            } else {
                btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${original}`;
            }
            btn.disabled = true;

            // Restaura se o form não navegar em 10s (fallback em caso de erro)
            setTimeout(() => {
                if (document.body.contains(btn)) {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            }, 10000);
        });
    },

    // ─── INIT ALL ───────────────────────────────────────────
    init() {
        this.initMasks();
        this.initCEP();
        this.initCNPJ();
        this.initAutocomplete();
        this.initPriceCalc();
        this.initDynamicItems();
        this.initImport();
        this.initBatch();
        this.initParcelas();
        this.initKeyboard();
        this.initConfirmDelete();
        this.initSubmitLoading();
    }
};

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', () => ERP.init());

// Inject autocomplete CSS
const acStyle = document.createElement('style');
acStyle.textContent = `
.autocomplete-dropdown{position:absolute;top:100%;left:0;right:0;z-index:1050;background:#fff;border:1px solid #e2e8f0;border-radius:0.5rem;box-shadow:0 4px 12px rgba(0,0,0,0.1);max-height:240px;overflow-y:auto;display:none}
.autocomplete-item{padding:0.6rem 0.85rem;cursor:pointer;font-size:0.875rem;transition:background 0.1s}
.autocomplete-item:hover,.autocomplete-item.active{background:#f1f5f9}
.autocomplete-empty{padding:0.75rem;text-align:center;color:#94a3b8;font-size:0.85rem}
@keyframes slideIn{from{opacity:0;transform:translateX(100%)}to{opacity:1;transform:translateX(0)}}
`;
document.head.appendChild(acStyle);
