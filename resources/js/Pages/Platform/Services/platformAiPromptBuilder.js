const FEATURE_PROMPT_SCHEMAS = Object.freeze({
    service_estimate_v1: {
        title: 'Pengaturan hasil estimasi',
        description: 'Atur isi draft estimasi yang akan dibaca tim operasional dan customer.',
        fields: [
            {
                key: 'max_items',
                type: 'number',
                label: 'Maksimal item estimasi',
                helper: 'Batasi jumlah jasa dan sparepart agar draft tetap ringkas.',
                min: 1,
                max: 12,
            },
            {
                key: 'review_focus',
                type: 'textarea',
                label: 'Fokus hasil',
                helper: 'Kalimat singkat tentang fokus utama estimasi.',
                placeholder: 'Contoh: utamakan item yang paling mungkin dibutuhkan agar approval customer lebih cepat.',
            },
            {
                key: 'prioritize_safety',
                type: 'checkbox',
                label: 'Utamakan item keselamatan',
                helper: 'AI akan lebih menonjolkan pekerjaan yang berdampak ke safety.',
            },
            {
                key: 'include_confidence',
                type: 'checkbox',
                label: 'Tampilkan confidence',
                helper: 'Menjaga ada skor keyakinan pada item dan hasil total.',
            },
            {
                key: 'include_item_reason',
                type: 'checkbox',
                label: 'Tampilkan alasan per item',
                helper: 'Isi deskripsi singkat kenapa item disarankan.',
            },
            {
                key: 'include_risk_notes',
                type: 'checkbox',
                label: 'Tampilkan risiko / perhatian',
                helper: 'AI menulis poin yang perlu dikomunikasikan ke customer.',
            },
            {
                key: 'include_advice',
                type: 'checkbox',
                label: 'Tampilkan saran tindak lanjut',
                helper: 'Berisi rekomendasi review untuk Service Advisor.',
            },
            {
                key: 'include_disclaimer',
                type: 'checkbox',
                label: 'Sertakan catatan wajib',
                helper: 'Menampilkan disclaimer bahwa hasil masih estimasi awal.',
            },
            {
                key: 'disclaimer_text',
                type: 'text',
                label: 'Isi catatan wajib',
                helper: 'Kalimat yang tampil sebagai catatan penutup hasil estimasi.',
                placeholder: 'Contoh: Estimasi awal, final setelah inspeksi teknisi.',
            },
        ],
    },
    sparepart_reorder_v1: {
        title: 'Pengaturan hasil reorder',
        description: 'Atur isi rekomendasi reorder agar siap direview owner atau purchasing.',
        fields: [
            {
                key: 'max_recommendations',
                type: 'number',
                label: 'Maksimal rekomendasi',
                helper: 'Batasi jumlah item reorder yang ditampilkan AI.',
                min: 1,
                max: 12,
            },
            {
                key: 'summary_focus',
                type: 'textarea',
                label: 'Fokus ringkasan',
                helper: 'Kalimat singkat untuk arah summary reorder.',
                placeholder: 'Contoh: utamakan item dengan risiko stockout tertinggi dan dampak operasional paling besar.',
            },
            {
                key: 'prioritize_fast_moving',
                type: 'checkbox',
                label: 'Utamakan item fast moving',
                helper: 'AI akan lebih fokus pada item dengan perputaran cepat.',
            },
            {
                key: 'include_priority',
                type: 'checkbox',
                label: 'Tampilkan prioritas',
                helper: 'Memakai label high, medium, atau low.',
            },
            {
                key: 'include_confidence',
                type: 'checkbox',
                label: 'Tampilkan confidence',
                helper: 'Menampilkan tingkat keyakinan tiap rekomendasi.',
            },
            {
                key: 'include_warnings',
                type: 'checkbox',
                label: 'Tampilkan catatan perhatian',
                helper: 'AI boleh menambahkan risiko cashflow, lead time, atau data kurang.',
            },
            {
                key: 'include_summary',
                type: 'checkbox',
                label: 'Tampilkan ringkasan akhir',
                helper: 'Menambah satu kalimat ringkasan siap baca.',
            },
            {
                key: 'reorder_unit_label',
                type: 'text',
                label: 'Label satuan reorder',
                helper: 'Contoh: pcs, botol, set.',
                placeholder: 'Contoh: pcs',
            },
        ],
    },
    symptom_diagnosis_v1: {
        title: 'Pengaturan hasil diagnosa awal',
        description: 'Atur bentuk hasil dugaan penyebab agar mudah dibaca frontdesk saat menjelaskan ke customer.',
        fields: [
            {
                key: 'max_possible_causes',
                type: 'number',
                label: 'Maksimal dugaan penyebab',
                helper: 'Batasi jumlah dugaan agar hasil tetap fokus dan tidak membingungkan.',
                min: 1,
                max: 6,
            },
            {
                key: 'diagnosis_focus',
                type: 'textarea',
                label: 'Fokus diagnosa',
                helper: 'Kalimat singkat untuk mengarahkan gaya diagnosa awal.',
                placeholder: 'Contoh: utamakan dugaan penyebab yang paling mungkin dan mudah dijelaskan ke customer.',
            },
            {
                key: 'prioritize_safety_risk',
                type: 'checkbox',
                label: 'Utamakan risiko keselamatan',
                helper: 'AI akan menaikkan prioritas dugaan yang berdampak ke safety.',
            },
            {
                key: 'include_confidence',
                type: 'checkbox',
                label: 'Tampilkan confidence',
                helper: 'Menampilkan tingkat keyakinan tiap dugaan penyebab.',
            },
            {
                key: 'include_recommended_checks',
                type: 'checkbox',
                label: 'Tampilkan langkah pengecekan',
                helper: 'Berisi daftar pengecekan awal untuk teknisi.',
            },
            {
                key: 'include_recommended_actions',
                type: 'checkbox',
                label: 'Tampilkan tindakan awal',
                helper: 'Berisi saran tindakan yang layak direview lebih lanjut.',
            },
            {
                key: 'include_warnings',
                type: 'checkbox',
                label: 'Tampilkan peringatan',
                helper: 'Menyorot risiko atau kondisi yang tidak aman untuk dipakai.',
            },
            {
                key: 'include_customer_advice',
                type: 'checkbox',
                label: 'Tampilkan saran ke customer',
                helper: 'AI menyiapkan poin komunikasi singkat yang mudah dipahami customer.',
            },
            {
                key: 'include_disclaimer',
                type: 'checkbox',
                label: 'Sertakan catatan wajib',
                helper: 'Menampilkan disclaimer bahwa hasil ini masih dugaan awal.',
            },
            {
                key: 'disclaimer_text',
                type: 'text',
                label: 'Isi catatan wajib',
                helper: 'Kalimat yang tampil sebagai penegasan bahwa hasil masih perlu inspeksi teknisi.',
                placeholder: 'Contoh: Diagnosa awal, hasil final setelah pemeriksaan teknisi.',
            },
        ],
    },
    monthly_business_report_v1: {
        title: 'Pengaturan laporan bulanan',
        description: 'Atur format ringkasan performa bulanan agar siap direview owner/manager bengkel.',
        fields: [
            {
                key: 'max_highlights',
                type: 'number',
                label: 'Maksimal highlights',
                helper: 'Batasi jumlah poin penting agar laporan tetap ringkas.',
                min: 1,
                max: 10,
            },
            {
                key: 'report_focus',
                type: 'textarea',
                label: 'Fokus laporan',
                helper: 'Kalimat singkat untuk mengarahkan insight utama laporan bulanan.',
                placeholder: 'Contoh: sorot tren omzet, efisiensi order servis, dan tindakan prioritas bulan berikutnya.',
            },
            {
                key: 'include_financial_summary',
                type: 'checkbox',
                label: 'Tampilkan ringkasan finansial',
                helper: 'Menampilkan omzet total, omzet jasa, omzet sparepart, dan estimasi laba kotor.',
            },
            {
                key: 'include_operational_summary',
                type: 'checkbox',
                label: 'Tampilkan ringkasan operasional',
                helper: 'Menampilkan total order, order selesai, dan customer baru.',
            },
            {
                key: 'include_risks',
                type: 'checkbox',
                label: 'Tampilkan risiko utama',
                helper: 'AI menulis poin risiko yang perlu diwaspadai bulan berikutnya.',
            },
            {
                key: 'include_recommendations',
                type: 'checkbox',
                label: 'Tampilkan rekomendasi aksi',
                helper: 'AI menulis langkah tindak lanjut yang konkret.',
            },
            {
                key: 'include_next_month_focus',
                type: 'checkbox',
                label: 'Tampilkan prioritas bulan depan',
                helper: 'AI menulis fokus kerja utama untuk periode selanjutnya.',
            },
            {
                key: 'include_disclaimer',
                type: 'checkbox',
                label: 'Sertakan catatan wajib',
                helper: 'Menampilkan disclaimer bahwa laporan AI adalah ringkasan awal.',
            },
            {
                key: 'disclaimer_text',
                type: 'text',
                label: 'Isi catatan wajib',
                helper: 'Kalimat disclaimer yang tampil di bagian akhir laporan.',
                placeholder: 'Contoh: Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.',
            },
        ],
    },
});

const DEFAULT_FEATURE_PROMPT_CONFIGS = Object.freeze({
    service_estimate_v1: {
        max_items: 6,
        prioritize_safety: true,
        include_confidence: true,
        include_risk_notes: true,
        include_advice: true,
        include_item_reason: true,
        include_disclaimer: true,
        disclaimer_text: 'Estimasi awal, final setelah inspeksi teknisi.',
        review_focus: 'Utamakan item yang paling mungkin dibutuhkan agar approval customer lebih cepat.',
    },
    sparepart_reorder_v1: {
        max_recommendations: 5,
        prioritize_fast_moving: true,
        include_priority: true,
        include_confidence: true,
        include_warnings: true,
        include_summary: true,
        reorder_unit_label: 'pcs',
        summary_focus: 'Utamakan item dengan risiko stockout tertinggi dan dampak operasional paling besar.',
    },
    symptom_diagnosis_v1: {
        max_possible_causes: 3,
        prioritize_safety_risk: true,
        include_confidence: true,
        include_recommended_checks: true,
        include_recommended_actions: true,
        include_warnings: true,
        include_customer_advice: true,
        include_disclaimer: true,
        disclaimer_text: 'Diagnosa awal, hasil final setelah pemeriksaan teknisi.',
        diagnosis_focus: 'Utamakan dugaan penyebab yang paling mungkin dan mudah dijelaskan ke customer.',
    },
    monthly_business_report_v1: {
        max_highlights: 5,
        include_financial_summary: true,
        include_operational_summary: true,
        include_risks: true,
        include_recommendations: true,
        include_next_month_focus: true,
        include_disclaimer: true,
        disclaimer_text: 'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.',
        report_focus: 'Sorot tren omzet, efisiensi order servis, dan tindakan prioritas untuk bulan berikutnya.',
    },
});

const TEST_INPUT_SCHEMAS = Object.freeze({
    service_estimate_v1: {
        title: 'Simulasi order servis',
        description: 'Isi skenario keluhan kendaraan seperti saat frontdesk menerima customer.',
    },
    sparepart_reorder_v1: {
        title: 'Simulasi stok sparepart',
        description: 'Isi periode analisis dan daftar sparepart untuk menguji rekomendasi reorder.',
    },
    symptom_diagnosis_v1: {
        title: 'Simulasi gejala kendaraan',
        description: 'Isi keluhan dan beberapa gejala yang disampaikan customer untuk menguji diagnosa awal.',
    },
    monthly_business_report_v1: {
        title: 'Simulasi laporan bulanan',
        description: 'Isi data ringkas performa bulanan bengkel untuk menguji hasil ringkasan AI.',
    },
});

const DEFAULT_TEST_INPUT_CONFIGS = Object.freeze({
    service_estimate_v1: {
        order: {
            complaint: 'Mesin bergetar saat langsam dan ada bunyi di area rem depan',
            odometer: 68500,
        },
        vehicle: {
            brand: 'Toyota',
            model: 'Avanza',
            year: 2019,
        },
        include_note: true,
        note: 'Customer minta estimasi sebelum pekerjaan dimulai.',
    },
    sparepart_reorder_v1: {
        period_days: 30,
        items: [
            {
                spare_part_name: 'Oli Mesin 10W-40',
                current_stock: 8,
                avg_daily_usage: 1.2,
                lead_time_days: 7,
            },
            {
                spare_part_name: 'Kampas Rem Depan',
                current_stock: 3,
                avg_daily_usage: 0.4,
                lead_time_days: 10,
            },
        ],
    },
    symptom_diagnosis_v1: {
        order: {
            complaint: 'Mesin brebet saat langsam dan tarikan terasa berat',
        },
        vehicle: {
            brand: 'Honda',
            model: 'Beat',
            year: 2021,
            odometer: 24500,
        },
        symptoms: [
            'Lampu indikator mesin kadang menyala',
            'Mesin terasa pincang saat berhenti',
            'Keluhan lebih terasa saat mesin panas',
        ],
        include_note: true,
        note: 'Customer meminta gambaran awal sebelum motor ditinggal.',
    },
    monthly_business_report_v1: {
        period: {
            month: 3,
            year: 2026,
        },
        revenue: {
            total: 125000000,
            service: 73000000,
            sparepart: 52000000,
            gross_profit_estimate: 38500000,
        },
        orders: {
            total: 186,
            completed: 172,
            pending: 14,
        },
        customers: {
            new: 48,
            returning: 97,
        },
        include_note: true,
        note: 'Bulan ini ada promo tune up dan keterlambatan suplai kampas rem.',
    },
});

const clampNumber = (value, min, max, fallback) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
        return fallback;
    }

    return Math.min(max, Math.max(min, Math.trunc(parsed)));
};

const normalizeBoolean = (value, fallback) => {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();

        if (['1', 'true', 'yes', 'on'].includes(normalized)) {
            return true;
        }

        if (['0', 'false', 'no', 'off', ''].includes(normalized)) {
            return false;
        }
    }

    if (value === null || value === undefined) {
        return fallback;
    }

    return Boolean(value);
};

const normalizeText = (value, fallback, maxLength = 240) => {
    if (typeof value !== 'string') {
        return fallback;
    }

    const normalized = value.replace(/\s+/g, ' ').trim();
    if (normalized === '') {
        return fallback;
    }

    return normalized.slice(0, maxLength);
};

const clampDecimal = (value, min, max, fallback, precision = 1) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
        return fallback;
    }

    const bounded = Math.min(max, Math.max(min, parsed));

    return Number(bounded.toFixed(precision));
};

const normalizeObject = (value) => {
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
};

const cloneValue = (value) => {
    return JSON.parse(JSON.stringify(value));
};

export const getFeaturePromptSchema = (featureKey) => {
    return FEATURE_PROMPT_SCHEMAS[String(featureKey || '')] || { title: '', description: '', fields: [] };
};

export const getDefaultFeaturePromptConfig = (featureKey) => {
    const defaults = DEFAULT_FEATURE_PROMPT_CONFIGS[String(featureKey || '')];

    return defaults ? { ...defaults } : {};
};

export const getTestInputSchema = (featureKey) => {
    return TEST_INPUT_SCHEMAS[String(featureKey || '')] || { title: '', description: '' };
};

export const getDefaultTestInputConfig = (featureKey) => {
    const defaults = DEFAULT_TEST_INPUT_CONFIGS[String(featureKey || '')];

    return defaults ? cloneValue(defaults) : {};
};

export const createTestInputListItem = (featureKey, listKey, index = 0) => {
    const normalizedFeatureKey = String(featureKey || '');
    const normalizedListKey = String(listKey || '');

    if (normalizedFeatureKey === 'sparepart_reorder_v1' && normalizedListKey === 'items') {
        return {
            spare_part_name: `Sparepart ${index + 1}`,
            current_stock: 0,
            avg_daily_usage: 0.5,
            lead_time_days: 7,
        };
    }

    if (normalizedFeatureKey === 'symptom_diagnosis_v1' && normalizedListKey === 'symptoms') {
        return `Gejala ${index + 1}`;
    }

    return {};
};

export const normalizeFeaturePromptConfig = (featureKey, rawConfig = {}) => {
    const normalizedFeatureKey = String(featureKey || '');
    const config = rawConfig && typeof rawConfig === 'object' && !Array.isArray(rawConfig) ? rawConfig : {};

    if (normalizedFeatureKey === 'service_estimate_v1') {
        return {
            max_items: clampNumber(config.max_items, 1, 12, 6),
            prioritize_safety: normalizeBoolean(config.prioritize_safety, true),
            include_confidence: normalizeBoolean(config.include_confidence, true),
            include_risk_notes: normalizeBoolean(config.include_risk_notes, true),
            include_advice: normalizeBoolean(config.include_advice, true),
            include_item_reason: normalizeBoolean(config.include_item_reason, true),
            include_disclaimer: normalizeBoolean(config.include_disclaimer, true),
            disclaimer_text: normalizeText(
                config.disclaimer_text,
                'Estimasi awal, final setelah inspeksi teknisi.',
            ),
            review_focus: normalizeText(
                config.review_focus,
                'Utamakan item yang paling mungkin dibutuhkan agar approval customer lebih cepat.',
            ),
        };
    }

    if (normalizedFeatureKey === 'sparepart_reorder_v1') {
        return {
            max_recommendations: clampNumber(config.max_recommendations, 1, 12, 5),
            prioritize_fast_moving: normalizeBoolean(config.prioritize_fast_moving, true),
            include_priority: normalizeBoolean(config.include_priority, true),
            include_confidence: normalizeBoolean(config.include_confidence, true),
            include_warnings: normalizeBoolean(config.include_warnings, true),
            include_summary: normalizeBoolean(config.include_summary, true),
            reorder_unit_label: normalizeText(config.reorder_unit_label, 'pcs', 40),
            summary_focus: normalizeText(
                config.summary_focus,
                'Utamakan item dengan risiko stockout tertinggi dan dampak operasional paling besar.',
            ),
        };
    }

    if (normalizedFeatureKey === 'symptom_diagnosis_v1') {
        return {
            max_possible_causes: clampNumber(config.max_possible_causes, 1, 6, 3),
            prioritize_safety_risk: normalizeBoolean(config.prioritize_safety_risk, true),
            include_confidence: normalizeBoolean(config.include_confidence, true),
            include_recommended_checks: normalizeBoolean(config.include_recommended_checks, true),
            include_recommended_actions: normalizeBoolean(config.include_recommended_actions, true),
            include_warnings: normalizeBoolean(config.include_warnings, true),
            include_customer_advice: normalizeBoolean(config.include_customer_advice, true),
            include_disclaimer: normalizeBoolean(config.include_disclaimer, true),
            disclaimer_text: normalizeText(
                config.disclaimer_text,
                'Diagnosa awal, hasil final setelah pemeriksaan teknisi.',
            ),
            diagnosis_focus: normalizeText(
                config.diagnosis_focus,
                'Utamakan dugaan penyebab yang paling mungkin dan mudah dijelaskan ke customer.',
            ),
        };
    }

    if (normalizedFeatureKey === 'monthly_business_report_v1') {
        return {
            max_highlights: clampNumber(config.max_highlights, 1, 10, 5),
            include_financial_summary: normalizeBoolean(config.include_financial_summary, true),
            include_operational_summary: normalizeBoolean(config.include_operational_summary, true),
            include_risks: normalizeBoolean(config.include_risks, true),
            include_recommendations: normalizeBoolean(config.include_recommendations, true),
            include_next_month_focus: normalizeBoolean(config.include_next_month_focus, true),
            include_disclaimer: normalizeBoolean(config.include_disclaimer, true),
            disclaimer_text: normalizeText(
                config.disclaimer_text,
                'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.',
            ),
            report_focus: normalizeText(
                config.report_focus,
                'Sorot tren omzet, efisiensi order servis, dan tindakan prioritas untuk bulan berikutnya.',
            ),
        };
    }

    return {};
};

export const normalizeTestInputConfig = (featureKey, rawConfig = {}) => {
    const normalizedFeatureKey = String(featureKey || '');
    const fallback = getDefaultTestInputConfig(normalizedFeatureKey);
    const config = normalizeObject(rawConfig);

    if (normalizedFeatureKey === 'service_estimate_v1') {
        const order = normalizeObject(config.order);
        const vehicle = normalizeObject(config.vehicle);
        const fallbackOrder = normalizeObject(fallback.order);
        const fallbackVehicle = normalizeObject(fallback.vehicle);
        const noteValue = typeof config.note === 'string' ? config.note : fallback.note;

        return {
            order: {
                complaint: normalizeText(
                    order.complaint ?? config.complaint,
                    fallbackOrder.complaint || '',
                ),
                odometer: clampNumber(
                    order.odometer ?? config.odometer,
                    0,
                    9999999,
                    fallbackOrder.odometer || 0,
                ),
            },
            vehicle: {
                brand: normalizeText(
                    vehicle.brand ?? config.brand,
                    fallbackVehicle.brand || '',
                    60,
                ),
                model: normalizeText(
                    vehicle.model ?? config.model,
                    fallbackVehicle.model || '',
                    60,
                ),
                year: clampNumber(
                    vehicle.year ?? config.year,
                    1990,
                    2100,
                    fallbackVehicle.year || new Date().getFullYear(),
                ),
            },
            include_note: normalizeBoolean(
                config.include_note,
                typeof noteValue === 'string' && noteValue.trim() !== '',
            ),
            note: normalizeText(noteValue, fallback.note || ''),
        };
    }

    if (normalizedFeatureKey === 'sparepart_reorder_v1') {
        const fallbackItems = Array.isArray(fallback.items) ? fallback.items : [];
        const itemsSource = Array.isArray(config.items) ? config.items : fallbackItems;
        const normalizedItems = itemsSource
            .map((item, index) => {
                const source = normalizeObject(item);
                const fallbackItem = normalizeObject(
                    fallbackItems[index] || createTestInputListItem(normalizedFeatureKey, 'items', index),
                );

                return {
                    spare_part_name: normalizeText(
                        source.spare_part_name,
                        fallbackItem.spare_part_name || `Sparepart ${index + 1}`,
                        80,
                    ),
                    current_stock: clampNumber(
                        source.current_stock,
                        0,
                        100000,
                        fallbackItem.current_stock || 0,
                    ),
                    avg_daily_usage: clampDecimal(
                        source.avg_daily_usage,
                        0,
                        10000,
                        fallbackItem.avg_daily_usage || 0,
                        1,
                    ),
                    lead_time_days: clampNumber(
                        source.lead_time_days,
                        0,
                        365,
                        fallbackItem.lead_time_days || 0,
                    ),
                };
            })
            .slice(0, 5);

        return {
            period_days: clampNumber(config.period_days, 1, 365, fallback.period_days || 30),
            items: normalizedItems.length > 0
                ? normalizedItems
                : [createTestInputListItem(normalizedFeatureKey, 'items', 0)],
        };
    }

    if (normalizedFeatureKey === 'symptom_diagnosis_v1') {
        const order = normalizeObject(config.order);
        const vehicle = normalizeObject(config.vehicle);
        const fallbackOrder = normalizeObject(fallback.order);
        const fallbackVehicle = normalizeObject(fallback.vehicle);
        const fallbackSymptoms = Array.isArray(fallback.symptoms) ? fallback.symptoms : [];
        const symptomsSource = Array.isArray(config.symptoms) ? config.symptoms : fallbackSymptoms;
        const noteValue = typeof config.note === 'string' ? config.note : fallback.note;
        const normalizedSymptoms = symptomsSource
            .map((symptom, index) => normalizeText(
                typeof symptom === 'string' ? symptom : '',
                String(createTestInputListItem(normalizedFeatureKey, 'symptoms', index) || ''),
                120,
            ))
            .slice(0, 6);

        return {
            order: {
                complaint: normalizeText(
                    order.complaint ?? config.complaint,
                    fallbackOrder.complaint || '',
                ),
            },
            vehicle: {
                brand: normalizeText(
                    vehicle.brand ?? config.brand,
                    fallbackVehicle.brand || '',
                    60,
                ),
                model: normalizeText(
                    vehicle.model ?? config.model,
                    fallbackVehicle.model || '',
                    60,
                ),
                year: clampNumber(
                    vehicle.year ?? config.year,
                    1990,
                    2100,
                    fallbackVehicle.year || new Date().getFullYear(),
                ),
                odometer: clampNumber(
                    vehicle.odometer ?? config.odometer,
                    0,
                    9999999,
                    fallbackVehicle.odometer || 0,
                ),
            },
            symptoms: normalizedSymptoms.length > 0
                ? normalizedSymptoms
                : [String(createTestInputListItem(normalizedFeatureKey, 'symptoms', 0) || 'Gejala 1')],
            include_note: normalizeBoolean(
                config.include_note,
                typeof noteValue === 'string' && noteValue.trim() !== '',
            ),
            note: normalizeText(noteValue, fallback.note || ''),
        };
    }

    if (normalizedFeatureKey === 'monthly_business_report_v1') {
        const period = normalizeObject(config.period);
        const revenue = normalizeObject(config.revenue);
        const orders = normalizeObject(config.orders);
        const customers = normalizeObject(config.customers);
        const fallbackPeriod = normalizeObject(fallback.period);
        const fallbackRevenue = normalizeObject(fallback.revenue);
        const fallbackOrders = normalizeObject(fallback.orders);
        const fallbackCustomers = normalizeObject(fallback.customers);
        const noteValue = typeof config.note === 'string' ? config.note : fallback.note;

        return {
            period: {
                month: clampNumber(
                    period.month ?? config.month,
                    1,
                    12,
                    fallbackPeriod.month || 1,
                ),
                year: clampNumber(
                    period.year ?? config.year,
                    2020,
                    2100,
                    fallbackPeriod.year || new Date().getFullYear(),
                ),
            },
            revenue: {
                total: clampNumber(
                    revenue.total ?? config.total_revenue,
                    0,
                    999999999999,
                    fallbackRevenue.total || 0,
                ),
                service: clampNumber(
                    revenue.service ?? config.service_revenue,
                    0,
                    999999999999,
                    fallbackRevenue.service || 0,
                ),
                sparepart: clampNumber(
                    revenue.sparepart ?? config.sparepart_revenue,
                    0,
                    999999999999,
                    fallbackRevenue.sparepart || 0,
                ),
                gross_profit_estimate: clampNumber(
                    revenue.gross_profit_estimate ?? config.gross_profit_estimate,
                    0,
                    999999999999,
                    fallbackRevenue.gross_profit_estimate || 0,
                ),
            },
            orders: {
                total: clampNumber(
                    orders.total ?? config.total_orders,
                    0,
                    999999,
                    fallbackOrders.total || 0,
                ),
                completed: clampNumber(
                    orders.completed ?? config.completed_orders,
                    0,
                    999999,
                    fallbackOrders.completed || 0,
                ),
                pending: clampNumber(
                    orders.pending ?? config.pending_orders,
                    0,
                    999999,
                    fallbackOrders.pending || 0,
                ),
            },
            customers: {
                new: clampNumber(
                    customers.new ?? config.new_customers,
                    0,
                    999999,
                    fallbackCustomers.new || 0,
                ),
                returning: clampNumber(
                    customers.returning ?? config.returning_customers,
                    0,
                    999999,
                    fallbackCustomers.returning || 0,
                ),
            },
            include_note: normalizeBoolean(
                config.include_note,
                typeof noteValue === 'string' && noteValue.trim() !== '',
            ),
            note: normalizeText(noteValue, fallback.note || ''),
        };
    }

    return {};
};

const buildServiceEstimatePrompt = (config) => {
    const maxItems = config.max_items || 6;

    return [
        'Buat draft estimasi awal berdasarkan data order servis, riwayat servis, dan katalog sparepart tenant.',
        'Tujuan hasil:',
        `- Maksimal ${maxItems} item kombinasi jasa dan sparepart.`,
        `- Fokus review: ${config.review_focus}`,
        config.prioritize_safety
            ? '- Prioritaskan pekerjaan yang berdampak ke keselamatan dan potensi kerusakan lanjutan.'
            : '- Prioritaskan item yang paling relevan dengan keluhan utama customer.',
        config.include_confidence
            ? '- Sertakan confidence 0-100 pada setiap item dan overall_confidence.'
            : '- Tetap sediakan field confidence, tetapi isi nilainya 0 agar struktur JSON konsisten.',
        config.include_item_reason
            ? '- Isi description dan reason singkat untuk tiap item agar frontdesk mudah menjelaskan ke customer.'
            : '- Kosongkan description dan reason dengan null jika penjelasan per item tidak dibutuhkan.',
        config.include_risk_notes
            ? '- Isi risk_notes dengan risiko atau perhatian yang perlu dikomunikasikan ke customer.'
            : '- Isi risk_notes dengan array kosong [].',
        config.include_advice
            ? '- Isi advice dengan saran tindak lanjut atau poin review Service Advisor.'
            : '- Isi advice dengan array kosong [].',
        config.include_disclaimer
            ? `- Isi disclaimer dengan kalimat ini: ${config.disclaimer_text}`
            : '- Isi disclaimer dengan null.',
        'Output WAJIB JSON valid tanpa teks tambahan dengan struktur:',
        '{',
        '  "items": [',
        '    {',
        '      "item_type": "service|sparepart",',
        '      "label": "string",',
        '      "description": "string|null",',
        '      "qty": 1,',
        '      "unit_label": "string|null",',
        '      "unit_price": 0,',
        '      "spare_part_name": "string|null",',
        '      "confidence": 0-100,',
        '      "reason": "string|null"',
        '    }',
        '  ],',
        '  "overall_confidence": 0-100,',
        '  "risk_notes": ["string"],',
        '  "advice": ["string"],',
        '  "disclaimer": "string|null"',
        '}',
        'Aturan:',
        '- Minimal 1 item jika ada dasar yang cukup dari input.',
        `- Maksimal ${maxItems} item.`,
        '- Jangan tambahkan field di luar struktur.',
        '- Untuk item service, qty selalu 1.',
        '- Untuk item sparepart, qty boleh > 1.',
        '- Jika data belum cukup, tetap beri estimasi awal paling masuk akal dan jelaskan keterbatasan pada risk_notes atau advice.',
    ].join('\n');
};

const buildReorderPrompt = (config) => {
    const maxRecommendations = config.max_recommendations || 5;

    return [
        'Analisis histori pemakaian sparepart dan stok saat ini untuk menghasilkan rekomendasi reorder.',
        'Tujuan hasil:',
        `- Maksimal ${maxRecommendations} rekomendasi reorder.`,
        `- Fokus ringkasan: ${config.summary_focus}`,
        config.prioritize_fast_moving
            ? '- Prioritaskan item fast-moving dan item yang berisiko stockout dalam lead time terdekat.'
            : '- Seimbangkan item fast-moving dengan item kritikal yang berdampak ke operasional bengkel.',
        config.include_priority
            ? '- Isi priority dengan high, medium, atau low.'
            : '- Isi priority dengan medium agar struktur tetap konsisten tanpa penekanan prioritas.',
        config.include_confidence
            ? '- Isi confidence 0-100 untuk tiap rekomendasi.'
            : '- Isi confidence dengan 0 agar struktur tetap konsisten.',
        config.include_warnings
            ? '- Isi warnings jika ada risiko cashflow, lead time, atau data yang kurang lengkap.'
            : '- Isi warnings dengan array kosong [].',
        config.include_summary
            ? '- Isi summary satu kalimat singkat yang siap dibaca owner atau tim purchasing.'
            : '- Isi summary dengan string kosong.',
        `- Gunakan satuan ${config.reorder_unit_label} saat menyarankan qty reorder.`,
        'Output WAJIB JSON valid tanpa teks tambahan dengan struktur:',
        '{',
        '  "recommendations": [',
        '    {',
        '      "spare_part_name": "string",',
        '      "current_stock": 0,',
        '      "suggested_reorder_qty": 0,',
        '      "priority": "high|medium|low",',
        '      "reason": "string",',
        '      "confidence": 0-100',
        '    }',
        '  ],',
        '  "warnings": ["string"],',
        '  "summary": "string"',
        '}',
        'Aturan:',
        '- Prioritaskan item dengan risiko stockout tinggi.',
        `- Maksimal ${maxRecommendations} rekomendasi.`,
        '- Hindari rekomendasi qty berlebihan tanpa alasan.',
        '- Jangan tambahkan field di luar struktur.',
    ].join('\n');
};

const buildSymptomDiagnosisPrompt = (config) => {
    const maxPossibleCauses = config.max_possible_causes || 3;

    return [
        'Analisis keluhan, gejala, dan data kendaraan untuk menyusun diagnosa awal yang bisa dibaca frontdesk.',
        'Tujuan hasil:',
        `- Maksimal ${maxPossibleCauses} dugaan penyebab.`,
        `- Fokus diagnosa: ${config.diagnosis_focus}`,
        config.prioritize_safety_risk
            ? '- Naikkan prioritas dugaan yang berdampak ke keselamatan atau risiko kerusakan lanjutan.'
            : '- Fokus pada dugaan yang paling mungkin sesuai pola gejala utama.',
        config.include_confidence
            ? '- Isi confidence 0-100 pada tiap dugaan penyebab.'
            : '- Isi confidence dengan 0 agar struktur tetap konsisten.',
        config.include_recommended_checks
            ? '- Isi recommended_checks untuk langkah inspeksi awal teknisi.'
            : '- Isi recommended_checks dengan array kosong [].',
        config.include_recommended_actions
            ? '- Isi recommended_actions untuk tindakan awal yang layak disarankan.'
            : '- Isi recommended_actions dengan array kosong [].',
        config.include_warnings
            ? '- Isi warnings untuk risiko, larangan pemakaian, atau tanda bahaya yang perlu segera disampaikan.'
            : '- Isi warnings dengan array kosong [].',
        config.include_customer_advice
            ? '- Isi customer_advice dengan saran singkat yang mudah dipahami customer.'
            : '- Isi customer_advice dengan array kosong [].',
        config.include_disclaimer
            ? `- Isi disclaimer dengan kalimat ini: ${config.disclaimer_text}`
            : '- Isi disclaimer dengan null.',
        'Output WAJIB JSON valid tanpa teks tambahan dengan struktur:',
        '{',
        '  "summary": "string",',
        '  "possible_causes": [',
        '    {',
        '      "label": "string",',
        '      "confidence": 0-100,',
        '      "severity": "high|medium|low",',
        '      "reason": "string",',
        '      "recommended_checks": ["string"],',
        '      "recommended_actions": ["string"]',
        '    }',
        '  ],',
        '  "warnings": ["string"],',
        '  "customer_advice": ["string"],',
        '  "disclaimer": "string|null"',
        '}',
        'Aturan:',
        '- Minimal 1 dugaan penyebab jika ada data gejala yang cukup.',
        `- Maksimal ${maxPossibleCauses} dugaan penyebab.`,
        '- Jangan tambahkan field di luar struktur.',
        '- Jangan menyatakan hasil sebagai kepastian final.',
        '- Gunakan bahasa yang mudah dipahami frontdesk dan customer.',
    ].join('\n');
};

const buildMonthlyBusinessReportPrompt = (config) => {
    const maxHighlights = config.max_highlights || 5;

    return [
        'Buat ringkasan laporan bulanan bengkel berdasarkan data finansial dan operasional yang diberikan.',
        'Tujuan hasil:',
        `- Maksimal ${maxHighlights} poin highlights.`,
        `- Fokus laporan: ${config.report_focus}`,
        config.include_financial_summary
            ? '- Tampilkan ringkasan finansial inti: omzet total, omzet jasa, omzet sparepart, dan estimasi laba kotor.'
            : '- Isi blok finansial dengan angka 0 agar struktur tetap konsisten.',
        config.include_operational_summary
            ? '- Tampilkan KPI operasional: total order, order selesai, order pending, dan customer baru.'
            : '- Isi KPI operasional dengan angka 0 agar struktur tetap konsisten.',
        config.include_risks
            ? '- Isi risks dengan poin risiko utama yang perlu diwaspadai bulan depan.'
            : '- Isi risks dengan array kosong [].',
        config.include_recommendations
            ? '- Isi recommendations dengan langkah tindak lanjut yang konkret dan bisa dieksekusi.'
            : '- Isi recommendations dengan array kosong [].',
        config.include_next_month_focus
            ? '- Isi next_month_focus dengan prioritas kerja bulan berikutnya.'
            : '- Isi next_month_focus dengan array kosong [].',
        config.include_disclaimer
            ? `- Isi disclaimer dengan kalimat ini: ${config.disclaimer_text}`
            : '- Isi disclaimer dengan null.',
        'Output WAJIB JSON valid tanpa teks tambahan dengan struktur:',
        '{',
        '  "period": "YYYY-MM",',
        '  "executive_summary": "string",',
        '  "highlights": ["string"],',
        '  "kpis": {',
        '    "total_revenue": 0,',
        '    "service_revenue": 0,',
        '    "sparepart_revenue": 0,',
        '    "gross_profit_estimate": 0,',
        '    "total_service_orders": 0,',
        '    "completed_service_orders": 0,',
        '    "new_customers": 0',
        '  },',
        '  "risks": ["string"],',
        '  "recommendations": ["string"],',
        '  "next_month_focus": ["string"],',
        '  "disclaimer": "string|null"',
        '}',
        'Aturan:',
        `- Maksimal ${maxHighlights} poin pada highlights.`,
        '- Fokus pada insight yang action-oriented, bukan sekadar ulang angka mentah.',
        '- Jangan tambahkan field di luar struktur.',
        '- Jika data input kurang lengkap, tetap beri ringkasan awal dan sebutkan keterbatasan pada executive_summary atau risks.',
    ].join('\n');
};

export const buildFeaturePromptFromConfig = (featureKey, rawConfig = {}) => {
    const normalizedFeatureKey = String(featureKey || '');
    const config = normalizeFeaturePromptConfig(normalizedFeatureKey, rawConfig);

    if (normalizedFeatureKey === 'service_estimate_v1') {
        return buildServiceEstimatePrompt(config);
    }

    if (normalizedFeatureKey === 'sparepart_reorder_v1') {
        return buildReorderPrompt(config);
    }

    if (normalizedFeatureKey === 'symptom_diagnosis_v1') {
        return buildSymptomDiagnosisPrompt(config);
    }

    if (normalizedFeatureKey === 'monthly_business_report_v1') {
        return buildMonthlyBusinessReportPrompt(config);
    }

    return '';
};

export const buildTestInputFromConfig = (featureKey, rawConfig = {}) => {
    const normalizedFeatureKey = String(featureKey || '');
    const config = normalizeTestInputConfig(normalizedFeatureKey, rawConfig);

    if (normalizedFeatureKey === 'service_estimate_v1') {
        const payload = {
            order: {
                complaint: config.order?.complaint || '',
                odometer: config.order?.odometer || 0,
            },
            vehicle: {
                brand: config.vehicle?.brand || '',
                model: config.vehicle?.model || '',
                year: config.vehicle?.year || new Date().getFullYear(),
            },
        };

        if (config.include_note && config.note) {
            payload.note = config.note;
        }

        return JSON.stringify(payload, null, 2);
    }

    if (normalizedFeatureKey === 'sparepart_reorder_v1') {
        return JSON.stringify({
            period_days: config.period_days || 30,
            items: Array.isArray(config.items) ? config.items : [],
        }, null, 2);
    }

    if (normalizedFeatureKey === 'symptom_diagnosis_v1') {
        const payload = {
            order: {
                complaint: config.order?.complaint || '',
            },
            vehicle: {
                brand: config.vehicle?.brand || '',
                model: config.vehicle?.model || '',
                year: config.vehicle?.year || new Date().getFullYear(),
                odometer: config.vehicle?.odometer || 0,
            },
            symptoms: Array.isArray(config.symptoms) ? config.symptoms : [],
        };

        if (config.include_note && config.note) {
            payload.note = config.note;
        }

        return JSON.stringify(payload, null, 2);
    }

    if (normalizedFeatureKey === 'monthly_business_report_v1') {
        const payload = {
            period: {
                month: config.period?.month || 1,
                year: config.period?.year || new Date().getFullYear(),
            },
            revenue: {
                total: config.revenue?.total || 0,
                service: config.revenue?.service || 0,
                sparepart: config.revenue?.sparepart || 0,
                gross_profit_estimate: config.revenue?.gross_profit_estimate || 0,
            },
            orders: {
                total: config.orders?.total || 0,
                completed: config.orders?.completed || 0,
                pending: config.orders?.pending || 0,
            },
            customers: {
                new: config.customers?.new || 0,
                returning: config.customers?.returning || 0,
            },
        };

        if (config.include_note && config.note) {
            payload.note = config.note;
        }

        return JSON.stringify(payload, null, 2);
    }

    return '';
};

export const resolveTestInputConfigFromTemplate = (featureKey, template) => {
    const normalizedFeatureKey = String(featureKey || '');

    if (typeof template === 'string' && template.trim() !== '') {
        try {
            const parsed = JSON.parse(template);
            return normalizeTestInputConfig(normalizedFeatureKey, parsed);
        } catch {
            return getDefaultTestInputConfig(normalizedFeatureKey);
        }
    }

    return getDefaultTestInputConfig(normalizedFeatureKey);
};
