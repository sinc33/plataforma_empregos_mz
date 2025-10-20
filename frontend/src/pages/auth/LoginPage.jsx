import React, { useState } from 'react';
import {
  Container,
  Paper,
  Box,
  Typography,
  TextField,
  Button,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Link,
  Alert,
  CircularProgress,
  Divider
} from '@mui/material';
import { useForm, Controller } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { Link as RouterLink, useNavigate, useLocation } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useSnackbar } from 'notistack';
import { useAuth } from '../../contexts/AuthContext';

const schema = yup.object({
  email: yup
    .string()
    .email('Digite um email válido')
    .required('Email é obrigatório'),
  password: yup
    .string()
    .min(6, 'Senha deve ter pelo menos 6 caracteres')
    .required('Senha é obrigatória'),
  userType: yup
    .string()
    .oneOf(['candidate', 'company', 'admin'], 'Tipo de usuário inválido')
    .required('Tipo de usuário é obrigatório')
});

function LoginPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { enqueueSnackbar } = useSnackbar();
  const { login } = useAuth();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const from = location.state?.from?.pathname || '/';

  const {
    control,
    handleSubmit,
    formState: { errors }
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
    setError('');

    try {
      const result = await login(data.email, data.password, data.userType);
      
      if (result.success) {
        enqueueSnackbar('Login realizado com sucesso!', { variant: 'success' });
        navigate(from, { replace: true });
      } else {
        setError(result.message || 'Erro no login');
      }
    } catch (error) {
      setError('Erro interno do sistema');
      console.error('Erro no login:', error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Entrar - Plataforma de Empregos Moçambique</title>
        <meta name="description" content="Acesse sua conta na maior plataforma de empregos de Moçambique" />
      </Helmet>

      <Container maxWidth="sm" sx={{ py: 4 }}>
        <Paper elevation={3} sx={{ p: 4 }}>
          <Box sx={{ textAlign: 'center', mb: 3 }}>
            <Typography variant="h4" component="h1" gutterBottom fontWeight="bold" color="primary">
              Bem-vindo de volta
            </Typography>
            <Typography variant="body1" color="text.secondary">
              Acesse sua conta para continuar
            </Typography>
          </Box>

          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}

          <Box component="form" onSubmit={handleSubmit(onSubmit)}>
            <Controller
              name="userType"
              control={control}
              render={({ field }) => (
                <FormControl fullWidth margin="normal" error={!!errors.userType}>
                  <InputLabel>Tipo de Conta</InputLabel>
                  <Select {...field} label="Tipo de Conta">
                    <MenuItem value="candidate">Candidato</MenuItem>
                    <MenuItem value="company">Empresa</MenuItem>
                    <MenuItem value="admin">Administrador</MenuItem>
                  </Select>
                  {errors.userType && (
                    <Typography variant="caption" color="error">
                      {errors.userType.message}
                    </Typography>
                  )}
                </FormControl>
              )}
            />

            <Controller
              name="email"
              control={control}
              render={({ field }) => (
                <TextField
                  {...field}
                  fullWidth
                  label="Email"
                  type="email"
                  margin="normal"
                  error={!!errors.email}
                  helperText={errors.email?.message}
                  autoComplete="email"
                />
              )}
            />

            <Controller
              name="password"
              control={control}
              render={({ field }) => (
                <TextField
                  {...field}
                  fullWidth
                  label="Senha"
                  type="password"
                  margin="normal"
                  error={!!errors.password}
                  helperText={errors.password?.message}
                  autoComplete="current-password"
                />
              )}
            />

            <Button
              type="submit"
              fullWidth
              variant="contained"
              size="large"
              disabled={isLoading}
              sx={{ mt: 3, mb: 2, py: 1.5 }}
            >
              {isLoading ? (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <CircularProgress size={20} color="inherit" />
                  Entrando...
                </Box>
              ) : (
                'Entrar'
              )}
            </Button>

            <Box sx={{ textAlign: 'center' }}>
              <Link
                component={RouterLink}
                to="/forgot-password"
                variant="body2"
                sx={{ mb: 2, display: 'block' }}
              >
                Esqueceu sua senha?
              </Link>

              <Divider sx={{ my: 2 }} />

              <Typography variant="body2" color="text.secondary">
                Não tem uma conta?{' '}
                <Link component={RouterLink} to="/register" variant="body2" fontWeight="bold">
                  Registre-se aqui
                </Link>
              </Typography>
            </Box>
          </Box>
        </Paper>
      </Container>
    </>
  );
}

export default LoginPage;