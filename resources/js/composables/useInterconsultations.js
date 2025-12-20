import { ref } from 'vue';
import { useApi } from './useApi';

export function useInterconsultations() {
    const { get, post, put, remove } = useApi();
    const interconsultations = ref([]);
    const loading = ref(false);
    const error = ref(null);

    const fetchInterconsultations = async (params = {}) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await get('/interconsultations', params);
            interconsultations.value = response.data;
        } catch (err) {
            error.value = err;
        } finally {
            loading.value = false;
        }
    };

    const getInterconsultation = async (id) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await get(`/interconsultations/${id}`);
            return response.data;
        } catch (err) {
            error.value = err;
        } finally {
            loading.value = false;
        }
    };

    const createInterconsultation = async (interconsultationData) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await post('/interconsultations', interconsultationData);
            return response.data;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updateInterconsultation = async (id, interconsultationData) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await put(`/interconsultations/${id}`, interconsultationData);
            return response.data;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const deleteInterconsultation = async (id) => {
        loading.value = true;
        error.value = null;
        try {
            await remove(`/interconsultations/${id}`);
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const respondToInterconsultation = async (id, responseData) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await post(`/interconsultations/${id}/respond`, responseData);
            return response.data;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const completeInterconsultation = async (id) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await post(`/interconsultations/${id}/complete`);
            return response.data;
        } catch (err) {
            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const getMyInterconsultations = async (params = {}) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await get('/my-interconsultations', params);
            interconsultations.value = response.data;
        } catch (err) {
            error.value = err;
        } finally {
            loading.value = false;
        }
    };

    const getInterconsultationsByPatient = async (patientId) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await get('/interconsultations', { patient_id: patientId });
            return response.data;
        } catch (err) {
            error.value = err;
        } finally {
            loading.value = false;
        }
    };

    return {
        interconsultations,
        loading,
        error,
        fetchInterconsultations,
        getInterconsultation,
        createInterconsultation,
        updateInterconsultation,
        deleteInterconsultation,
        respondToInterconsultation,
        completeInterconsultation,
        getMyInterconsultations,
        getInterconsultationsByPatient
    };
}
