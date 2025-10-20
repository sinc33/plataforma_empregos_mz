import React, { useState } from 'react';
import {
  Container,
  Paper,
  Box,
  Typography,
  TextField,
  Button,
  Tabs,
  Tab,
  Alert,
  CircularProgress,
  Divider,
  Link,
  FormControlLabel,
  Checkbox
} from '@mui/material';
import { useForm, Controller } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useSnackbar } from 'notistack';
import { useAuth } from '../../contexts/AuthContext';

const candidateSchema = yup.object({
  name: yup.string().required('Nome é obrigatório'),
  email: yup.string().email('Email inválido').required('Email é obrigatório'),
  password: yup.string().min(6, 'Senha deve ter pelo menos 6 caracteres').required('Senha é obrigatória'),
  confirmPassword: yup.string().oneOf([yup.ref('password')], 'Senhas não coincidem').required('Confirme a senha'),
  phone: yup.string().required('Telefone é obrigatório'),
  acceptTerms: yup.boolean().oneOf([true], 'Deve aceitar os termos')
});

const companySchema = yup.object({
  companyName: yup.string().required('Nome da empresa é obrigatório'),
  email: yup.string().email('Email inválido').required('Email é obrigatório'),
  password: yup.string().min(6, 'Senha deve ter pelo menos 6 caracteres').required('Senha é obrigatória'),
  confirmPassword: yup.string().oneOf([yup.ref('password')], 'Senhas não coincidem').required('Confirme a senha'),
  contactPerson: yup.string().required('Pessoa de contacto é obrigatória'),
  phone: yup.string().required('Telefone é obrigatório'),
  website: yup.string().url('URL inválida'),
  description: yup.string().required('Descrição da empresa é obrigatória'),
  acceptTerms: yup.boolean().oneOf([true], 'Deve aceitar os termos')
});

function TabPanel({ children, value, index, ...other }) {
  return (
    <div role="tabpanel" hidden={value !== index} {...other}>
      {value === index && <Box sx={{ pt: 3 }}>{children}</Box>}
    </div>
  );
}

function RegisterPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { enqueueSnackbar } = useSnackbar();
  const { register } = useAuth();
  const [tabValue, setTabValue] = useState(searchParams.get('type') === 'company' ? 1 : 0);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const candidateForm = useForm({
    resolver: yupResolver(candidateSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
      confirmPassword: '',
      phone: '',
      acceptTerms: false
    }
  });

  const companyForm = useForm({
    resolver: yupResolver(companySchema),
    defaultValues: {
      companyName: '',
      email: '',
      password: '',
      confirmPassword: '',
      contactPerson: '',
      phone: '',
      website: '',
      description: '',
      acceptTerms: false
    }
  });

  const handleTabChange = (event, newValue) => {
    setTabValue(newValue);
    setError('');
  };

  const onSubmitCandidate = async (data) => {
    setIsLoading(true);
    setError('');

    try {
      const result = await register({
        name: data.name,
        email: data.email,
        password: data.password,
        phone: data.phone
      }, 'candidate');
      
      if (result.success) {
        enqueueSnackbar('Conta criada com sucesso!', { variant: 'success' });
        navigate('/candidate/dashboard');
      } else {
        setError(result.message || 'Erro no registo');
      }
    } catch (error) {
      setError('Erro interno do sistema');
      console.error('Erro no registo:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const onSubmitCompany = async (data) => {
    setIsLoading(true);
    setError('');

    try {
      const result = await register({
        company_name: data.companyName,
        email: data.email,
        password: data.password,
        contact_person: data.contactPerson,
        phone: data.phone,
        website: data.website,
        description: data.description
      }, 'company');
      
      if (result.success) {
        enqueueSnackbar('Empresa registada com sucesso!', { variant: 'success' });
        navigate('/company/dashboard');
      } else {
        setError(result.message || 'Erro no registo');
      }
    } catch (error) {
      setError('Erro interno do sistema');
      console.error('Erro no registo:', error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Criar Conta - Plataforma de Empregos Moçambique</title>
        <meta name="description" content="Crie sua conta gratuita e acesse as melhores oportunidades de emprego em Moçambique" />
      </Helmet>

      <Container maxWidth="md" sx={{ py: 4 }}>
        <Paper elevation={3} sx={{ overflow: 'hidden' }}>
          <Box sx={{ textAlign: 'center', p: 4, pb: 2 }}>
            <Typography variant="h4" component="h1" gutterBottom fontWeight="bold" color="primary">
              Criar Nova Conta
            </Typography>
            <Typography variant="body1" color="text.secondary">
              Junte-se à maior comunidade profissional de Moçambique
            </Typography>
          </Box>

          <Tabs
            value={tabValue}
            onChange={handleTabChange}
            centered
            sx={{
              borderBottom: 1,
              borderColor: 'divider',
              '& .MuiTab-root': {
                fontSize: '1.1rem',
                fontWeight: 600,
                textTransform: 'none',
                minWidth: 200
              }
            }}
          >
            <Tab label="Sou Candidato" />
            <Tab label="Sou Empresa" />
          </Tabs>

          {error && (
            <Alert severity="error" sx={{ mx: 4, mt: 2 }}>
              {error}
            </Alert>
          )}

          {/* Formulário do Candidato */}
          <TabPanel value={tabValue} index={0}>
            <Box sx={{ p: 4 }}>
              <Box component="form" onSubmit={candidateForm.handleSubmit(onSubmitCandidate)}>
                <Controller
                  name="name"
                  control={candidateForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Nome Completo"
                      margin="normal"
                      error={!!candidateForm.formState.errors.name}
                      helperText={candidateForm.formState.errors.name?.message}
                    />
                  )}
                />

                <Controller
                  name="email"
                  control={candidateForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Email"
                      type="email"
                      margin="normal"
                      error={!!candidateForm.formState.errors.email}
                      helperText={candidateForm.formState.errors.email?.message}
                    />
                  )}
                />

                <Controller
                  name="phone"
                  control={candidateForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Telefone"
                      margin="normal"
                      error={!!candidateForm.formState.errors.phone}
                      helperText={candidateForm.formState.errors.phone?.message}
                    />
                  )}
                />

                <Controller
                  name="password"
                  control={candidateForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Senha"
                      type="password"
                      margin="normal"
                      error={!!candidateForm.formState.errors.password}
                      helperText={candidateForm.formState.errors.password?.message}
                    />
                  )}
                />

                <Controller
                  name="confirmPassword"
                  control={candidateForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Confirmar Senha"
                      type="password"
                      margin="normal"
                      error={!!candidateForm.formState.errors.confirmPassword}
                      helperText={candidateForm.formState.errors.confirmPassword?.message}
                    />
                  )}
                />

                <Controller
                  name="acceptTerms"
                  control={candidateForm.control}
                  render={({ field }) => (
                    <FormControlLabel
                      control={<Checkbox {...field} checked={field.value} />}
                      label={
                        <Typography variant="body2">
                          Aceito os{' '}
                          <Link href="#" underline="hover">
                            termos de uso
                          </Link>{' '}
                          e{' '}
                          <Link href="#" underline="hover">
                            política de privacidade
                          </Link>
                        </Typography>
                      }
                      sx={{ mt: 2, mb: 1 }}
                    />
                  )}
                />
                {candidateForm.formState.errors.acceptTerms && (
                  <Typography variant="caption" color="error" display="block">
                    {candidateForm.formState.errors.acceptTerms.message}
                  </Typography>
                )}

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
                      Criando conta...
                    </Box>
                  ) : (
                    'Criar Conta de Candidato'
                  )}
                </Button>
              </Box>
            </Box>
          </TabPanel>

          {/* Formulário da Empresa */}
          <TabPanel value={tabValue} index={1}>
            <Box sx={{ p: 4 }}>
              <Box component="form" onSubmit={companyForm.handleSubmit(onSubmitCompany)}>
                <Controller
                  name="companyName"
                  control={companyForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Nome da Empresa"
                      margin="normal"
                      error={!!companyForm.formState.errors.companyName}
                      helperText={companyForm.formState.errors.companyName?.message}
                    />
                  )}
                />

                <Controller
                  name="email"
                  control={companyForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Email da Empresa"
                      type="email"
                      margin="normal"
                      error={!!companyForm.formState.errors.email}
                      helperText={companyForm.formState.errors.email?.message}
                    />
                  )}
                />

                <Controller
                  name="contactPerson"
                  control={companyForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Pessoa de Contacto"
                      margin="normal"
                      error={!!companyForm.formState.errors.contactPerson}
                      helperText={companyForm.formState.errors.contactPerson?.message}
                    />
                  )}
                />

                <Controller
                  name="phone"
                  control={companyForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Telefone"
                      margin="normal"
                      error={!!companyForm.formState.errors.phone}
                      helperText={companyForm.formState.errors.phone?.message}
                    />
                  )}
                />

                <Controller
                  name="website"
                  control={companyForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Website (opcional)"
                      margin="normal"
                      error={!!companyForm.formState.errors.website}
                      helperText={companyForm.formState.errors.website?.message}
                    />
                  )}
                />

                <Controller
                  name="description"
                  control={companyForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Descrição da Empresa"
                      multiline
                      rows={3}
                      margin="normal"
                      error={!!companyForm.formState.errors.description}
                      helperText={companyForm.formState.errors.description?.message}
                    />
                  )}
                />

                <Controller
                  name="password"
                  control={companyForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Senha"
                      type="password"
                      margin="normal"
                      error={!!companyForm.formState.errors.password}
                      helperText={companyForm.formState.errors.password?.message}
                    />
                  )}
                />

                <Controller
                  name="confirmPassword"
                  control={companyForm.control}
                  render={({ field }) => (
                    <TextField
                      {...field}
                      fullWidth
                      label="Confirmar Senha"
                      type="password"
                      margin="normal"
                      error={!!companyForm.formState.errors.confirmPassword}
                      helperText={companyForm.formState.errors.confirmPassword?.message}
                    />
                  )}
                />

                <Controller
                  name="acceptTerms"
                  control={companyForm.control}
                  render={({ field }) => (
                    <FormControlLabel
                      control={<Checkbox {...field} checked={field.value} />}
                      label={
                        <Typography variant="body2">
                          Aceito os{' '}
                          <Link href="#" underline="hover">
                            termos de uso
                          </Link>{' '}
                          e{' '}
                          <Link href="#" underline="hover">
                            política de privacidade
                          </Link>
                        </Typography>
                      }
                      sx={{ mt: 2, mb: 1 }}
                    />
                  )}
                />
                {companyForm.formState.errors.acceptTerms && (
                  <Typography variant="caption" color="error" display="block">
                    {companyForm.formState.errors.acceptTerms.message}
                  </Typography>
                )}

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
                      Criando conta...
                    </Box>
                  ) : (
                    'Criar Conta de Empresa'
                  )}
                </Button>
              </Box>
            </Box>
          </TabPanel>

          <Divider />
          
          <Box sx={{ textAlign: 'center', p: 3 }}>
            <Typography variant="body2" color="text.secondary">
              Já tem uma conta?{' '}
              <Link component={RouterLink} to="/login" variant="body2" fontWeight="bold">
                Faça login aqui
              </Link>
            </Typography>
          </Box>
        </Paper>
      </Container>
    </>
  );
}

export default RegisterPage;