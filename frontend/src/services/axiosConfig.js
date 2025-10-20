import axios from 'axios';

// Configuração base do Axios
const axiosConfig = axios.create({
  baseURL: process.env.NODE_ENV === 'production' 
    ? 'https://your-domain.com/api' 
    : 'http://localhost:8000/plataforma_emprego_mz',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// Interceptor para requisições
axiosConfig.interceptors.request.use(
  (config) => {
    // Adicionar token de autorização se existir
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    // Log da requisição em desenvolvimento
    if (process.env.NODE_ENV === 'development') {
      console.log('Axios Request:', {
        url: config.url,
        method: config.method,
        data: config.data,
        headers: config.headers
      });
    }
    
    return config;
  },
  (error) => {
    console.error('Erro na configuração da requisição:', error);
    return Promise.reject(error);
  }
);

// Interceptor para respostas
axiosConfig.interceptors.response.use(
  (response) => {
    // Log da resposta em desenvolvimento
    if (process.env.NODE_ENV === 'development') {
      console.log('Axios Response:', {
        url: response.config.url,
        status: response.status,
        data: response.data
      });
    }
    
    return response;
  },
  (error) => {
    // Tratar erros comuns
    if (error.response) {
      const { status, data } = error.response;
      
      switch (status) {
        case 401:
          // Token expirado ou inválido
          localStorage.removeItem('token');
          delete axiosConfig.defaults.headers.common['Authorization'];
          
          // Redirecionar para login se não estiver na página de login
          if (window.location.pathname !== '/login') {
            window.location.href = '/login';
          }
          break;
          
        case 403:
          console.error('Acesso negado:', data.message);
          break;
          
        case 404:
          console.error('Recurso não encontrado:', error.config.url);
          break;
          
        case 422:
          console.error('Dados inválidos:', data.errors || data.message);
          break;
          
        case 500:
          console.error('Erro interno do servidor');
          break;
          
        default:
          console.error('Erro HTTP:', status, data.message);
      }
    } else if (error.request) {
      console.error('Erro de rede - sem resposta do servidor:', error.request);
    } else {
      console.error('Erro na configuração da requisição:', error.message);
    }
    
    return Promise.reject(error);
  }
);

export default axiosConfig;
