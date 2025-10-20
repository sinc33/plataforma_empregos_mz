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
  Stack,
  CircularProgress
} from '@mui/material';
import {
  Email as EmailIcon,
  ArrowBack as ArrowBackIcon,
  CheckCircle as CheckCircleIcon
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
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
    .required('Email é obrigatório')
});

function ForgotPasswordPage() {
  const navigate = useNavigate();
  const { enqueueSnackbar } = useSnackbar();
  const { forgotPassword } = useAuth();
  
  const [isLoading, setIsLoading] = useState(false);
  const [emailSent, setEmailSent] = useState(false);
  const [sentEmail, setSentEmail] = useState('');

  const {
    control,
    handleSubmit,
    formState: { errors },
    setError
  } = useForm({
    resolver: yupResolver(schema),
    defaultValues: {
      email: ''
    }
  });

  const onSubmit = async (data) => {
    setIsLoading(true);
    
    try {
      const result = await forgotPassword(data.email);
      
      if (result.success) {
        setEmailSent(true);
        setSentEmail(data.email);
        enqueueSnackbar('Email de recuperação enviado!', { variant: 'success' });
      } else {
        setError('root', {
          type: 'manual',
          message: result.message || 'Erro ao enviar email de recuperação.'
        });
      }
    } catch (error) {
      console.error('Erro na recuperação de senha:', error);
      setError('root', {
        type: 'manual',
        message: 'Erro interno. Tente novamente mais tarde.'
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleBackToLogin = () => {
    navigate('/login');
  };

  if (emailSent) {
    return (
      <>
        <Helmet>
          <title>Email Enviado - Plataforma de Empregos Moçambique</title>
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
                backdropFilter: 'blur(10px)',
                textAlign: 'center'
              }}
            >
              <CheckCircleIcon 
                sx={{ 
                  fontSize: 80, 
                  color: 'success.main', 
                  mb: 2 
                }} 
              />
              
              <Typography 
                variant="h4" 
                component="h1" 
                fontWeight="bold" 
                color="primary" 
                gutterBottom
              >
                Email Enviado!
              </Typography>
              
              <Typography variant="body1" color="text.secondary" sx={{ mb: 3 }}>
                Enviamos um link de recuperação de senha para:
              </Typography>
              
              <Typography 
                variant="h6" 
                color="primary" 
                fontWeight="600" 
                sx={{ mb: 4 }}
              >
                {sentEmail}
              </Typography>
              
              <Alert severity="info" sx={{ mb: 4, textAlign: 'left' }}>
                <Typography variant="body2">
                  • Verifique a sua caixa de entrada e pasta de spam<br />
                  • O link é válido por 1 hora<br />
                  • Se não recebeu o email, tente novamente em alguns minutos
                </Typography>
              </Alert>
              
              <Stack spacing={2}>
                <Button
                  variant="contained"
                  size="large"
                  onClick={() => {
                    setEmailSent(false);
                    setSentEmail('');
                  }}
                  sx={{ py: 1.5 }}
                >
                  Enviar Novamente
                </Button>
                
                <Button
                  variant="outlined"
                  size="large"
                  onClick={handleBackToLogin}
                  startIcon={<ArrowBackIcon />}
                  sx={{ py: 1.5 }}
                >
                  Voltar ao Login
                </Button>
              </Stack>
            </Paper>
          </Container>
        </Box>
      </>
    );
  }

  return (
    <>
      <Helmet>
        <title>Recuperar Senha - Plataforma de Empregos Moçambique</title>
        <meta name="description" content="Recupere a senha da sua conta na Plataforma de Empregos de Moçambique" />
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
                Recuperar Senha
              </Typography>
              <Typography variant="body1" color="text.secondary">
                Digite seu email para receber um link de recuperação
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
                      placeholder="Digite o email da sua conta"
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

                {/* Botão de Envio */}
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
                      Enviando...
                    </>
                  ) : (
                    'Enviar Link de Recuperação'
                  )}
                </Button>

                {/* Link para voltar ao login */}
                <Button
                  variant="outlined"
                  size="large"
                  onClick={handleBackToLogin}
                  startIcon={<ArrowBackIcon />}
                  sx={{ py: 1.5 }}
                >
                  Voltar ao Login
                </Button>
              </Stack>
            </Box>

            {/* Informações adicionais */}
            <Alert severity="info" sx={{ mt: 4 }}>
              <Typography variant="body2">
                <strong>Problemas com a recuperação?</strong><br />
                Entre em contato conosco em{' '}
                <Link href="mailto:suporte@empregomz.com" color="primary">
                  suporte@empregomz.com
                </Link>
              </Typography>
            </Alert>
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

export default ForgotPasswordPage;