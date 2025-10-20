import axiosConfig from './axiosConfig';

// Auth endpoints
export const authApi = {
  me: () => axiosConfig.get('/auth/me'),
  login: (data) => axiosConfig.post('/auth/login', data),
  register: (data) => axiosConfig.post('/auth/register', data),
  logout: () => axiosConfig.post('/auth/logout'),
  forgotPassword: (email) => axiosConfig.post('/auth/forgot-password', { email }),
};

// Candidate endpoints
export const candidateApi = {
  getProfile: () => axiosConfig.get('/candidate/profile'),
  updateProfile: (payload) => axiosConfig.put('/candidate/profile', payload),
  uploadCV: (file) => {
    const form = new FormData();
    form.append('cv', file);
    return axiosConfig.post('/candidate/profile/cv', form, { headers: { 'Content-Type': 'multipart/form-data' } });
  },
  getApplications: (params) => axiosConfig.get('/candidate/applications', { params }),
};

// Company endpoints
export const companyApi = {
  getProfile: () => axiosConfig.get('/company/profile'),
  updateProfile: (payload) => axiosConfig.put('/company/profile', payload),
  getJobs: (params) => axiosConfig.get('/company/jobs', { params }),
  createJob: (payload) => axiosConfig.post('/company/jobs', payload),
  getJob: (id) => axiosConfig.get(`/company/jobs/${id}`),
  updateJob: (id, payload) => axiosConfig.put(`/company/jobs/${id}`),
  archiveJob: (id) => axiosConfig.post(`/company/jobs/${id}/archive`),
  getCandidatesByJob: (id, params) => axiosConfig.get(`/company/jobs/${id}/candidates`, { params }),
  updateApplicationStatus: (applicationId, status) => axiosConfig.put(`/company/applications/${applicationId}/status`, { status }),
};

// Public jobs endpoints
export const jobsApi = {
  list: (params) => axiosConfig.get('/jobs', { params }),
  detail: (id) => axiosConfig.get(`/jobs/${id}`),
  apply: (id, payload) => axiosConfig.post(`/jobs/${id}/apply`, payload),
};

// Admin endpoints
export const adminApi = {
  users: (params) => axiosConfig.get('/admin/users', { params }),
  updateUser: (id, payload) => axiosConfig.put(`/admin/users/${id}`, payload),
  jobs: (params) => axiosConfig.get('/admin/jobs', { params }),
  updateJob: (id, payload) => axiosConfig.put(`/admin/jobs/${id}`, payload),
  companies: (params) => axiosConfig.get('/admin/companies', { params }),
  approveCompany: (id) => axiosConfig.post(`/admin/companies/${id}/approve`),
  rejectCompany: (id) => axiosConfig.post(`/admin/companies/${id}/reject`),
};
