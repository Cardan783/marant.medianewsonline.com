var app = new Vue({
	el: '#app',
	data: {
	  message: 'Hola Vue!',
	  startTime: "",
      endTime: "23:59",
	  startDate: "",
	  endDate: "",
	  startDia: null,
	  startMes: null,
	  startAño: null,
	  startFecha: null,
	  endDia: null,
	  endMes: null,
	  endAño: null,
	  endFecha: null,
	  temps: 20.0,
	  pres: 1.5,
	  volts: 12.0,
	  temperaturaData: 0.0,
	  temp: 0.0,
	  pre: 0.0,
	  volt: 0.0,
	  chart: null,
	  sensorsCount: null,
	  all_data:[],
	  log:[]
	 },
	   created: function(){
			  console.log("Iniciando datos…");
			  this.onDateOrTimeChanged();
			  this.get_datos_sensores();
			  this.startDate = this.getStartMonthDate();
			  this.endDate = this.getEndMonthDate(); 
			  //this.endTime = this.getStartTime();
        		this.startTime = this.getStartTime();
		  },
		  methods:{
			  get_datos_sensores: function(){
				  console.log("get_datos_sensores");
				  fetch("./conexion.php")
				  .then(response=>response.json())
				  .then(json=>{this.all_data=json.sensores})
			  },
			  async onDateOrTimeChanged() {
				//console.log("get_datos_sensores");
				fetch("./conexion.php")
				.then(response=>response.json())
				.then(json=>{this.all_data=json.sensores})
				  console.log("onDateOrTimeChanged");

				  // Lógica para el formato de visualización (DD-MM-YYYY)
				  this.startDia = this.startDate.slice(8);
				  this.startMes = this.startDate.slice(5,-3);
				  this.startAño = this.startDate.slice(0,4);
				  this.startFecha = this.startDia.concat("-",this.startMes,"-",this.startAño);

				  this.endDia = this.endDate.slice(8);
				  this.endMes = this.endDate.slice(5,-3);
				  this.endAño = this.endDate.slice(0,4);
				  this.endFecha = this.endDia.concat("-",this.endMes,"-",this.endAño);

				  // Construir la URL de consulta.
                  // Ahora enviamos fecha (YYYY-MM-DD) y hora.
				  const url = `get_data.php?
				  	start=${this.startDate}
                    &startTime=${this.startTime}
					&end=${this.endDate}
                    &endTime=${this.endTime}
					&temp=${this.temps}
					&pre=${this.pres}
					&volt=${this.volts}`;
				  const response = await fetch(url);
				  this.log = await response.json();
				  //console.log(this.log[0].Temperatura);  //con esto podemos ver en consola
				//return log;
  				/*
				  const labels = log.map(d => {
					  
					  return d.Fecha;
				  });
				  this.temperaturaData = log.map(d => {
					  this.temps = d.Temperatura;
					  //console.log(this.temps);
					  return d.Temperatura;
				  });
				  const presionData = log.map(d => {
					  this.pres = d.Presion;
					  return d.Presion;
				  });
				  const voltajeData = log.map(d => {
					  this.volts = d.Voltaje;
					  //return d.Voltaje;
				  });
				  //this.refreshChart(labels, temperaturaData, presionData);
				*/
			  },
			  getStartMonthDate() {
			  console.log("getStartMonthDate");
				  const d = new Date();
				  console.log(d);
				  return this.formatDate(new Date(d.getFullYear(), d.getMonth(), 1));
			  },
			  getEndMonthDate() {
			  console.log("getEndMonthDate");
				  const d = new Date();
				  return this.formatDate(new Date(d.getFullYear(), d.getMonth() + 1, 0));
			  },
			  getStartTime() {
				console.log("getStartTime");
				const d = new Date();
				return this.formatTime(new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0));
			},
			  formatDate(date) {
			  console.log("formatDate");
			  console.log(date);
				  const month = date.getMonth() + 1;
				  const day = date.getDate();
				  return `${date.getFullYear()}-${this.padWithZero(month)}-${this.padWithZero(day)}`;
			  },
			  formatTime(date) {
				console.log("formatTime");
				const hours = date.getHours();
				const minutes = date.getMinutes();
				const seconds = date.getSeconds();
				return `${this.padWithZero(hours)}:${this.padWithZero(minutes)}:${this.padWithZero(seconds)}`;
			},
			    padWithZero(value) {
            return (value < 10 ? "0" : "").concat(value);
        },
			  },
		  computed: {
  lastSensor(){
   this.sensorsCount = this.log.length;
  },
  ultimo_registro() { 
  	                    
	  this.temp =this.all_data[0].Temperatura ; 
	  this.pre =this.all_data[0].Presion ; 
	  this.volt =this.all_data[0].Voltaje ; 
	  
  },
  color_temp(){
			 return{ 
				  'bg-success' : this.temp <=28,
				  'bg-primary' : this.temp >28 &&  this.temp <30,
				  'bg-danger' : this.temp >=30
			 } 
			 },
  color_pre(){
			 return{ 
				  'bg-success' : this.pre <1.5,
				  'bg-primary' : this.pre >=1.5 &&  this.pre <5.5,
				  'bg-danger' : this.pre >=5.5
			 } 
			 },
  color_volt(){
			 return{ 
				  'bg-success' : this.volt <=24,
				  'bg-primary' : this.volt >24 &&  this.volt <28,
				  'bg-danger' : this.volt >=28
			 } 
			 }	
  },
  
   mounted() {
	  
	  this.intervalId = setInterval(this.onDateOrTimeChanged, 5000); // 30000 milisegundos son 30 segundos 
  },
  beforeDestroy() {
	  clearInterval(this.intervalId); 
  },
  
	  });