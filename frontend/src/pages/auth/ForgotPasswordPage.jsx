import React, { useState } from 'react';
import { Container, Paper, Box, Typography, TextField, Button, Link, Alert } from '@mui/material';
import { useForm, Controller } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { Link as RouterLink } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useAuth } from '../../contexts/AuthContext';

const schema = yup.object({
  email: yup.string().email('Email inválido').required('Email é obrigatório'),
});

function ForgotPasswordPage() {
  const { forgotPassword } = useAuth();
  const [status, setStatus] = useState({ type: '', message: '' });

  const { control, handleSubmit, formState: { errors } } = useForm({
    resolver: yupResolver(schema),
    defaultValues: { email: '' }
  });

  const onSubmit = async ({ email }) => {
    const res = await forgotPassword(email);
    if (res.success) setStatus({ type: 'success', message: res.message || 'Email de recuperação enviado.' });
    else setStatus({ type: 'error', message: res.message || 'Não foi possível enviar o email.' });
  }

  return (
    <>
      <Helmet>
        <title>Recuperar Senha</title>
      </Helmet>
      <Container maxWidth="sm" sx={{ py: 4 }}>
        <Paper sx={{ p: 4 }}>
          <Typography variant="h4" fontWeight="bold" color="primary" gutterBottom>
            Recuperar Senha
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
            Informe o email associado à sua conta para enviar um link de recuperação.
          </Typography>

          {status.message && (
            <Alert severity={status.type} sx={{ mb: 2 }}>{status.message}</Alert>
          )}

          <Box component="form" onSubmit={handleSubmit(onSubmit)}>
            <Controller
              name="email"
              control={control}
              render={({ field }) => (
                <TextField {...field} fullWidth label="Email" type="email" margin="normal" error={!!errors.email} helperText={errors.email?.message} />
              )}
            />
            <Button type="submit" fullWidth variant="contained" size="large" sx={{ mt: 2 }}>Enviar</Button>
          </Box>

          <Box sx={{ mt: 3, textAlign: 'center' }}>
            <Link component={RouterLink} to="/login">Voltar ao login</Link>
          </Box>
        </Paper>
      </Container>
    </>
  );
}

export default ForgotPasswordPage;
