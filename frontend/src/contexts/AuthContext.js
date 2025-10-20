import React, { createContext, useContext, useReducer, useEffect } from 'react';
import axiosConfig from '../services/axiosConfig';

const AuthContext = createContext();

// Estados de autenticação
const initialState = {
  user: null,
  isAuthenticated: false,
  isLoading: true,
  userType: null // 'candidate', 'company', 'admin'
};

// Reducer para gerenciar estados
function authReducer(state, action) {
  switch (action.type) {
    case 'LOGIN_SUCCESS':
      return {
        ...state,
        user: action.payload.user,
        userType: action.payload.userType,
        isAuthenticated: true,
        isLoading: false
      };
    case 'LOGOUT':
      return {
        ...state,
        user: null,
        userType: null,
        isAuthenticated: false,
        isLoading: false
      };
    case 'SET_LOADING':
      return {
        ...state,
        isLoading: action.payload
      };
    case 'UPDATE_PROFILE':
      return {
        ...state,
        user: { ...state.user, ...action.payload }
      };
    default:
      return state;
  }
}

// Provider do contexto de autenticação
export function AuthProvider({ children }) {
  const [state, dispatch] = useReducer(authReducer, initialState);

  // Verificar se o usuário está logado ao carregar a aplicação
  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const token = localStorage.getItem('token');
      if (token) {
        // Configurar token no axios
        axiosConfig.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        
        // Verificar se o token é válido
        const response = await axiosConfig.get('/auth/me');
        if (response.data.success) {
          dispatch({
            type: 'LOGIN_SUCCESS',
            payload: {
              user: response.data.user,
              userType: response.data.user.type
            }
          });
        } else {
          throw new Error('Token inválido');
        }
      } else {
        dispatch({ type: 'SET_LOADING', payload: false });
      }
    } catch (error) {
      console.error('Erro ao verificar autenticação:', error);
      localStorage.removeItem('token');
      delete axiosConfig.defaults.headers.common['Authorization'];
      dispatch({ type: 'SET_LOADING', payload: false });
    }
  };

  // Função de login
  const login = async (email, password, userType) => {
    try {
      dispatch({ type: 'SET_LOADING', payload: true });
      
      const response = await axiosConfig.post('/auth/login', {
        email,
        password,
        user_type: userType
      });

      if (response.data.success) {
        const { token, user } = response.data;
        
        // Salvar token no localStorage
        localStorage.setItem('token', token);
        
        // Configurar token no axios
        axiosConfig.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        
        dispatch({
          type: 'LOGIN_SUCCESS',
          payload: {
            user,
            userType: user.type
          }
        });
        
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Erro no login');
      }
    } catch (error) {
      dispatch({ type: 'SET_LOADING', payload: false });
      return {
        success: false,
        message: error.response?.data?.message || error.message || 'Erro no login'
      };
    }
  };

  // Função de registo
  const register = async (userData, userType) => {
    try {
      dispatch({ type: 'SET_LOADING', payload: true });
      
      const response = await axiosConfig.post('/auth/register', {
        ...userData,
        user_type: userType
      });

      if (response.data.success) {
        // Após registo bem-sucedido, fazer login automático
        return await login(userData.email, userData.password, userType);
      } else {
        throw new Error(response.data.message || 'Erro no registo');
      }
    } catch (error) {
      dispatch({ type: 'SET_LOADING', payload: false });
      return {
        success: false,
        message: error.response?.data?.message || error.message || 'Erro no registo'
      };
    }
  };

  // Função de logout
  const logout = async () => {
    try {
      await axiosConfig.post('/auth/logout');
    } catch (error) {
      console.error('Erro no logout:', error);
    } finally {
      // Limpar dados locais independentemente do resultado da API
      localStorage.removeItem('token');
      delete axiosConfig.defaults.headers.common['Authorization'];
      dispatch({ type: 'LOGOUT' });
    }
  };

  // Função para atualizar perfil
  const updateProfile = (updatedData) => {
    dispatch({
      type: 'UPDATE_PROFILE',
      payload: updatedData
    });
  };

  // Função para recuperação de senha
  const forgotPassword = async (email) => {
    try {
      const response = await axiosConfig.post('/auth/forgot-password', { email });
      return {
        success: response.data.success,
        message: response.data.message
      };
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Erro ao enviar email de recuperação'
      };
    }
  };

  const value = {
    ...state,
    login,
    register,
    logout,
    updateProfile,
    forgotPassword,
    checkAuthStatus
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

// Hook personalizado para usar o contexto
export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth deve ser usado dentro de um AuthProvider');
  }
  return context;
}

export default AuthContext;
