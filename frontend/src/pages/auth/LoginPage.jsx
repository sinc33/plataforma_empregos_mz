import React, { useState } from 'react';
import {
  Box,
  Container,
  Paper,
  TextField,
  Button,
  Typography,
  Link,
  Alert,
  InputAdornment,
  IconButton,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Divider,
  Stack,
  CircularProgress
} from '@mui/material';
import {
  Visibility,
  VisibilityOff,
  Email as EmailIcon,
  Lock as LockIcon,
  Person as PersonIcon
} from '@mui/icons-material';
import { useNavigate, useLocation } from 'react-router-dom';
import { useForm, Controller } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { useSnackbar } from 'notistack';
import { Helmet } from 'react-helmet-async';
import { useAuth } from '../../contexts/AuthContext';

// Schema de validação
const schema = yup.object().shape({
  email: yup
    .string()
    .email('Email inválido')
    .required('Email é obrigatório'),
  password: yup
    .string()
    .min(6, 'Senha deve ter pelo menos 6 caracteres')
    .required('Senha é obrigatória'),
  userType: yup
    .string()
    .oneOf(['candidate', 'company', 'admin'], 'Tipo de utilizador inválido')
    .required('Selecione o tipo de utilizador')
});

function LoginPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { enqueueSnackbar } = useSnackbar();
  const { login } = useAuth();
  
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  
  // URL de redirecionamento após login
  const from = location.state?.from?.pathname || '/';

  const {
    control,
    handleSubmit,
    formState: { errors },
    setError
  } = useForm({
    resolver: yupResolver(schema),
    defaultValues: {
      email: '',
      password: '',
      userType: 'candidate'
    }
  });

  const onSubmit = async (data) => {
    setIsLoading(true);
    
    try {
      const result = await login(data.email, data.password, data.userType);
      
      if (result.success) {
        enqueueSnackbar('Login realizado com sucesso!', { variant: 'success' });
        
        // Redirecionar baseado no tipo de utilizador
        let redirectPath = from;
        if (from === '/') {
          switch (data.userType) {
            case 'candidate':
              redirectPath = '/candidate/dashboard';
              break;
            case 'company':
              redirectPath = '/company/dashboard';
              break;
            case 'admin':
              redirectPath = '/admin/dashboard';
              break;
            default:
              redirectPath = '/';
          }
        }
        
        navigate(redirectPath, { replace: true });
      } else {
        setError('root', {
          type: 'manual',
          message: result.message || 'Erro no login. Verifique suas credenciais.'
        });
      }
    } catch (error) {
      console.error('Erro no login:', error);
      setError('root', {
        type: 'manual',
        message: 'Erro interno. Tente novamente mais tarde.'
      });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Login - Plataforma de Empregos Moçambique</title>
        <meta name="description" content="Faça login na sua conta da Plataforma de Empregos de Moçambique" />
      </Helmet>

      <Box
        sx={{
          minHeight: '100vh',
          display: 'flex',
          alignItems: 'center',
          background: 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)'
        }}
      >
        <Container maxWidth="sm">
          <Paper 
            elevation={10}
            sx={{
              p: { xs: 3, sm: 6 },
              borderRadius: 3,
              background: 'rgba(255,255,255,0.95)',
              backdropFilter: 'blur(10px)'
            }}
          >
            {/* Header */}
            <Box sx={{ textAlign: 'center', mb: 4 }}>
              <Typography 
                variant="h4" 
                component="h1" 
                fontWeight="bold" 
                color="primary" 
                gutterBottom
              >
                🇲🇿 Emprego MZ
              </Typography>
              <Typography variant="h5" fontWeight="600" gutterBottom>
                Bem-vindo de volta
              </Typography>
              <Typography variant="body1" color="text.secondary">
                Entre na sua conta para continuar
              </Typography>
            </Box>

            {/* Formulário */}
            <Box component="form" onSubmit={handleSubmit(onSubmit)}>
              {/* Erro geral */}
              {errors.root && (
                <Alert severity="error" sx={{ mb: 3 }}>
                  {errors.root.message}
                </Alert>
              )}

              <Stack spacing={3}>
                {/* Tipo de Utilizador */}
                <Controller
                  name="userType"
                  control={control}
                  render={({ field }) => (
                    <FormControl fullWidth error={!!errors.userType}>
                      <InputLabel id="userType-label">
                        Tipo de Utilizador
                      </InputLabel>
                      <Select
                        {...field}
                        labelId="userType-label"
                        label="Tipo de Utilizador"
                        startAdornment={
                          <InputAdornment position="start">
                            <PersonIcon color={errors.userType ? 'error' : 'action'} />
                          </InputAdornment>
                        }
                      >
                        <MenuItem value="candidate">Candidato</MenuItem>
                        <MenuItem value="company">Empresa</MenuItem>
                        <MenuItem value="admin">Administrador</MenuItem>
                      </Select>
                      {errors.userType && (
                        <Typography variant="caption" color="error" sx={{ mt: 0.5, ml: 2 }}>
                          {errors.userType.message}
                        </Typography>
                      )}
                    </FormControl>
                  )}
                />

                {/* Email */}
                <Controller
                  name="email"
                  control={control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Email"
                      type="email"
                      error={!!errors.email}
                      helperText={errors.email?.message}
                      InputProps={{
                        startAdornment: (
                          <InputAdornment position="start">
                            <EmailIcon color={errors.email ? 'error' : 'action'} />
                          </InputAdornment>
                        )
                      }}
                    />
                  )}
                />

                {/* Senha */}
                <Controller
                  name="password"
                  control={control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Senha"
                      type={showPassword ? 'text' : 'password'}
                      error={!!errors.password}
                      helperText={errors.password?.message}
                      InputProps={{
                        startAdornment: (
                          <InputAdornment position="start">
                            <LockIcon color={errors.password ? 'error' : 'action'} />
                          </InputAdornment>
                        ),
                        endAdornment: (
                          <InputAdornment position="end">
                            <IconButton
                              onClick={() => setShowPassword(!showPassword)}
                              edge="end"
                              aria-label="toggle password visibility"
                            >
                              {showPassword ? <VisibilityOff /> : <Visibility />}
                            </IconButton>
                          </InputAdornment>
                        )
                      }}
                    />
                  )}
                />

                {/* Botão de Login */}
                <Button
                  type="submit"
                  fullWidth
                  variant="contained"
                  size="large"
                  disabled={isLoading}
                  sx={{ 
                    mt: 3, 
                    py: 1.5,
                    fontSize: '1.1rem',
                    fontWeight: 600
                  }}
                >
                  {isLoading ? (
                    <>
                      <CircularProgress size={20} sx={{ mr: 1 }} />
                      Entrando...
                    </>
                  ) : (
                    'Entrar'
                  )}
                </Button>

                {/* Link para recuperar senha */}
                <Box sx={{ textAlign: 'center' }}>
                  <Link
                    component="button"
                    type="button"
                    variant="body2"
                    onClick={(e) => {
                      e.preventDefault();
                      navigate('/forgot-password');
                    }}
                    sx={{ 
                      textDecoration: 'none',
                      '&:hover': { textDecoration: 'underline' }
                    }}
                  >
                    Esqueceu a senha?
                  </Link>
                </Box>
              </Stack>
            </Box>

            <Divider sx={{ my: 4 }}>
              <Typography variant="body2" color="text.secondary">
                ou
              </Typography>
            </Divider>

            {/* Links para registo */}
            <Box sx={{ textAlign: 'center' }}>
              <Typography variant="body2" color="text.secondary" gutterBottom>
                Não tem uma conta?
              </Typography>
              <Stack 
                direction={{ xs: 'column', sm: 'row' }} 
                spacing={2} 
                justifyContent="center"
                sx={{ mt: 2 }}
              >
                <Button 
                  variant="outlined" 
                  onClick={() => navigate('/register?type=candidate')}
                  sx={{ minWidth: 140 }}
                >
                  Sou Candidato
                </Button>
                <Button 
                  variant="outlined" 
                  onClick={() => navigate('/register?type=company')}
                  sx={{ minWidth: 140 }}
                >
                  Sou Empresa
                </Button>
              </Stack>
            </Box>
          </Paper>

          {/* Footer */}
          <Typography 
            variant="body2" 
            color="text.secondary" 
            align="center" 
            sx={{ mt: 4 }}
          >
            © 2025 Plataforma de Empregos Moçambique. Todos os direitos reservados.
          </Typography>
        </Container>
      </Box>
    </>
  );
}

export default LoginPage;