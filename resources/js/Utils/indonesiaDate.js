const INDONESIA_LOCALE = 'id-ID';
const INDONESIA_TIMEZONE = 'Asia/Jakarta';

const toDate = (value) => {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

export const formatDateIndonesia = (value, options = {}) => {
    const date = toDate(value);

    if (!date) {
        return '-';
    }

    return new Intl.DateTimeFormat(INDONESIA_LOCALE, {
        timeZone: INDONESIA_TIMEZONE,
        dateStyle: 'long',
        ...options,
    }).format(date);
};

export const formatDateTimeIndonesia = (value, options = {}) => {
    const date = toDate(value);

    if (!date) {
        return '-';
    }

    return new Intl.DateTimeFormat(INDONESIA_LOCALE, {
        timeZone: INDONESIA_TIMEZONE,
        dateStyle: 'long',
        timeStyle: 'short',
        ...options,
    }).format(date);
};
