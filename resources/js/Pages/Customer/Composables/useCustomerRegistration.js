import { formatCpfCnpj, formatPhone } from "@/Utils/formatters";
import { usePage } from "@inertiajs/vue3";
import axios from "axios";
import { useToast } from "primevue/usetoast";
import { computed, ref } from "vue";

export function useCustomerRegistration(customerRegistrationMode) {
    const toast = useToast();
    const page = usePage();

    const settings = computed(() => page.props.companySettings || page.props.company_settings || {});
    const reqWhatsapp = computed(() => settings.value.require_whatsapp !== false && settings.value.require_whatsapp !== 0 && settings.value.require_whatsapp !== '0');
    const reqBirthDate = computed(() => settings.value.require_birth_date === true || settings.value.require_birth_date === 1 || settings.value.require_birth_date === '1');
    const allowMinors = computed(() => settings.value.allow_minors !== false && settings.value.allow_minors !== 0 && settings.value.allow_minors !== '0');

    const form = ref({ name: "", phone: "", cpf: "", birth_date: null, email: "", password: "" });
    const serproStep = ref('cpf_input');
    const isLoadingSerpro = ref(false);

    const existingAccount = ref(null);

    const formatCpfInput = (e) => {
        let val = e?.target?.value !== undefined ? e.target.value : e;
        if (typeof val !== 'string') return;
        form.value.cpf = formatCpfCnpj(val);
        if (e?.target) e.target.value = form.value.cpf;
    };

    const formatPhoneInput = (e) => {
        let val = e?.target?.value !== undefined ? e.target.value : e;
        if (typeof val !== 'string') return;
        form.value.phone = formatPhone(val);
        if (e?.target) e.target.value = form.value.phone;
    };

    const calculateAge = (dob) => {
        if (!dob) return 0;
        const diff = Date.now() - new Date(dob).getTime();
        return Math.abs(new Date(diff).getUTCFullYear() - 1970);
    };

    const isCpfValid = computed(() => {
        let strCPF = String(form.value.cpf || '').replace(/[^\d]+/g, '');
        if (strCPF.length !== 11) return false;
        if (/^(\d)\1+$/.test(strCPF)) return false;

        let soma = 0, resto;
        for (let i = 1; i <= 9; i++) soma += parseInt(strCPF.substring(i - 1, i)) * (11 - i);
        resto = (soma * 10) % 11;
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(strCPF.substring(9, 10))) return false;

        soma = 0;
        for (let i = 1; i <= 10; i++) soma += parseInt(strCPF.substring(i - 1, i)) * (12 - i);
        resto = (soma * 10) % 11;
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(strCPF.substring(10, 11))) return false;

        return true;
    });

    const isPhoneValid = computed(() => {
        const rawPhone = String(form.value.phone || '').replace(/\D/g, '');
        return rawPhone.length >= 10 && rawPhone.length <= 11;
    });

    const isMinorError = computed(() => {
        if (!allowMinors.value && form.value.birth_date) {
            return calculateAge(form.value.birth_date) < 18;
        }
        return false;
    });

    const isFormValid = computed(() => {
        const hasName = Boolean(form.value.name && form.value.name.trim().length >= 2);
        const hasPhone = isPhoneValid.value;
        const hasBirth = form.value.birth_date !== null && form.value.birth_date !== "";

        let valid = hasName;

        if (reqWhatsapp.value) valid = valid && hasPhone;
        if (reqBirthDate.value) valid = valid && hasBirth;
        if (isMinorError.value) valid = false;

        const mode = (typeof customerRegistrationMode === 'object' && customerRegistrationMode?.value)
            ? customerRegistrationMode.value
            : (customerRegistrationMode || 'basic');

        const rawCpf = String(form.value.cpf || '').replace(/\D/g, '');

        // 🚀 Exige CPF estritamente se for cpf_required ou customer_only
        if (mode === 'cpf_required' || mode === 'customer_only') {
            return valid && isCpfValid.value;
        }

        // Modo Serpro (consulta na Receita Federal)
        if (mode === 'serpro_magic') {
            return (serproStep.value === 'confirm_data' || serproStep.value === 'manual_fallback') && valid;
        }

        // Modos opcionais (basic, etc.): se começou a digitar CPF, valida; se vazio, libera
        if (rawCpf.length > 0) {
            return valid && isCpfValid.value;
        }

        return valid;
    });

    const lookupSerpro = async () => {
        isLoadingSerpro.value = true;
        try {
            const cleanCpf = String(form.value.cpf || '').replace(/\D/g, '');
            form.value.cpf = formatCpfCnpj(cleanCpf);

            const response = await axios.get(route('api.customers.check-cpf'), { params: { cpf: cleanCpf } });

            if (response.data.available === false) {
                existingAccount.value = response.data;
                serproStep.value = 'cpf_exists';
                return;
            }

            const payload = response.data.data || response.data;
            const fetchedName = payload.nome || payload.name;
            const fetchedDob = payload.nascimento || payload.birth_date;

            if (fetchedName) {
                form.value.name = fetchedName;
                form.value.birth_date = fetchedDob;
                serproStep.value = 'confirm_data';
            } else {
                serproStep.value = 'manual_fallback';
            }
        } catch (e) {
            serproStep.value = 'manual_fallback';
        } finally {
            isLoadingSerpro.value = false;
        }
    };

    const resetForm = (initialData = {}) => {
        const seedName = typeof initialData === 'string' ? initialData : (initialData?.name || "");
        form.value = {
            name: seedName,
            phone: initialData?.phone ? formatPhone(initialData.phone) : "",
            cpf: initialData?.cpf || "",
            birth_date: initialData?.birth_date || null,
            email: initialData?.email || "",
            password: ""
        };
        existingAccount.value = null;
        serproStep.value = 'cpf_input';
    };

    return {
        form, serproStep, isLoadingSerpro, isCpfValid, isPhoneValid, isFormValid, isMinorError, existingAccount,
        reqWhatsapp, reqBirthDate, allowMinors,
        formatCpfInput, formatPhoneInput, lookupSerpro, resetForm
    };
}
