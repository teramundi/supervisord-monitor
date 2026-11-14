from xmlrpc.server import SimpleXMLRPCServer
from xmlrpc.client import Fault
import time

class Supervisor:
    def __init__(self):
        self.running = True
        self.pid = 100
        self.log = '\x1b[32mfixture log\x1b[0m'
    def getSupervisorVersion(self): return '4.2.5'
    def getAllProcessInfo(self):
        return [dict(name='worker', group='worker', statename='RUNNING' if self.running else 'STOPPED',
                     description='pid %s, uptime 0:01:00' % self.pid, pid=self.pid if self.running else 0,
                     stderr_logfile='/tmp/fixture.err', start=int(time.time()))]
    def readProcessStderrLog(self, name, offset, length): return self.log
    def startProcess(self, name, wait=True): self.running=True; self.pid+=1; return True
    def stopProcess(self, name, wait=True): self.running=False; return True
    def startAllProcesses(self, wait=True): self.startProcess('worker'); return []
    def stopAllProcesses(self, wait=True): self.stopProcess('worker'); return []
    def clearProcessLogs(self, name): self.log=''; return True

server=SimpleXMLRPCServer(('0.0.0.0',9001),allow_none=True,logRequests=False)
obj=Supervisor()
for name in ('getSupervisorVersion','getAllProcessInfo','readProcessStderrLog','startProcess','stopProcess','startAllProcesses','stopAllProcesses','clearProcessLogs'):
    server.register_function(getattr(obj,name),'supervisor.'+name)
server.serve_forever()
